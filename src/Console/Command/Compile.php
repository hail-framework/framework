<?php

namespace Hail\Console\Command;

use Hail\Console\Command;
use Hail\Console\Option\OptionCollection;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Phar;

/**
 * Compile package to phar file.
 *
 * phar file structure
 *
 * {{Stub}}
 *    {{ClassLoader}}
 *    {{Bin or Executable or Bootstrap}}
 * {{Halt Compiler}}
 * {{Content Section}}
 */
class Compile extends Command
{
    public function brief()
    {
        return 'compile current source into Phar format library file.';
    }

    public function init()
    {
        // optional classloader script (use Universal ClassLoader by default
        $this->addOption('classloader?', 'embed classloader source file');
        // append executable (bootstrap scripts, if it's not defined, it's just a library phar file.
        $this->addOption('bootstrap?', 'bootstrap or executable source file');

        $this->addOption('executable', 'is a executable script ?');
        $this->addOption('lib+', 'library path');
        $this->addOption('include+', 'include path');
        $this->addOption('exclude+', 'exclude pattern');
        $this->addOption('output:', 'output');
        $this->addOption('c|compress?', 'phar file compress type: gz, bz2');
        $this->addOption('no-compress', 'do not compress phar file.');
    }


    public function execute()
    {
        ini_set('phar.readonly', 0);

        $logger = $this->logger;

        $bootstrap = $this->getOption('bootstrap');
        $lib_dirs = $this->getOption('lib') ?: ['src'];
        $output = $this->getOption('output') ?: 'output.phar';
        $classloader = $this->getOption('classloader');

        $logger->notice('Compiling Phar...');

        $pharFile = $output;

        $logger->debug("Creating phar file $pharFile...");

        $phar = new Phar($pharFile, 0, $pharFile);
        $phar->setSignatureAlgorithm(Phar::SHA1);
        $phar->startBuffering();

        $excludePatterns = $this->getOption('exclude');
        if ($includes = $this->getOption('include')) {
            foreach ($includes as $include) {
                $phar->buildFromIterator(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($include)),
                    getcwd()
                );
            }
        }

        // archive library directories into phar file.
        foreach ($lib_dirs as $src_dir) {
            if (!file_exists($src_dir)) {
                die("$src_dir does not exist.");
            }

            $src_dir = realpath($src_dir);
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src_dir),
                RecursiveIteratorIterator::CHILD_FIRST);

            // compile php file only (currently)
            foreach ($iterator as $path) {
                if ($path->isFile()) {
                    $rel_path = substr($path->getPathname(), strlen($src_dir) + 1);

                    if ($excludePatterns) {
                        $exclude = false;
                        foreach ($excludePatterns as $pattern) {
                            if (preg_match('#' . $pattern . '#', $rel_path)) {
                                $exclude = true;
                                break;
                            }
                        }
                        if ($exclude) {
                            $logger->debug('exclude ' . $rel_path);
                            continue;
                        }
                    }

                    // if it's php file.
                    if (preg_match('/\.php$/', $path->getFilename())) {
                        $content = php_strip_whitespace($path->getRealPath());
                        # echo $path->getPathname() . "\n";
                        $logger->debug('add ' . $rel_path);
                        $phar->addFromString($rel_path, $content);
                    } else {
                        $logger->debug('add ' . $rel_path);
                        $phar->addFile($path->getPathname(), $rel_path);
                    }
                }
            }
        }

        // Including bootstrap file
        if ($bootstrap) {
            $logger->info("Compile $bootstrap");
            $content = php_strip_whitespace($bootstrap);
            $content = preg_replace('{^#!/usr/bin/env\s+php\s*}', '', $content);
            $phar->addFromString($bootstrap, $content);
        }

        $stub = '';
        if ($this->getOption('executable')) {
            $logger->debug('Adding shell bang...');
            $stub .= "#!/usr/bin/env php\n";
        }

        $logger->notice('Setting up stub...');
        $stub .= <<<"EOT"
<?php
Phar::mapPhar('$pharFile');
EOT;

        // use stream to resolve Universal\ClassLoader\Autoloader;
        if ($classloader) {
            $logger->notice('Adding classloader...');

            if (is_string($classloader) && file_exists($classloader)) {
                $content = php_strip_whitespace($classloader);
                $phar->addFromString($classloader, $content);
                $stub .= <<<"EOT"
require 'phar://$pharFile/$classloader';
EOT;
            } else {
                $classloader_interface = 'Universal/ClassLoader/ClassLoader.php';
                $classloader = 'Universal/ClassLoader/SplClassLoader.php';
                $stub .= <<<"EOT"
require 'phar://$pharFile/$classloader_interface';
require 'phar://$pharFile/$classloader';
\$classLoader = new \\Universal\\ClassLoader\\SplClassLoader;
\$classLoader->addFallback( 'phar://$pharFile' );
\$classLoader->register(true);
EOT;
            }
        }

        if ($bootstrap) {
            $logger->info('Adding bootstrap script...');
            $stub .= <<<"EOT"
require 'phar://$pharFile/$bootstrap';
EOT;
        }

        $stub .= <<<"EOT"
__HALT_COMPILER();
EOT;

        $phar->setStub($stub);
        $phar->stopBuffering();

        $compress_type = Phar::GZ;
        if ($this->getOption('no-compress')) {
            $compress_type = null;
        } elseif ($v = $this->getOption('compress')) {
            switch ($v) {
                case 'gz':
                    $compress_type = Phar::GZ;
                    break;
                case 'bz2':
                    $compress_type = Phar::BZ2;
                    break;
                default:
                    throw new \InvalidArgumentException("Compress type: $v is not supported, valids are gz, bz2");
                    break;
            }
        }

        if ($compress_type) {
            $logger->notice('Compressing phar ...');
            $phar->compressFiles($compress_type);
        }

        $logger->notice('Done');
    }
}
