<?php
/**
 * Phinx
 *
 * (The MIT license)
 * Copyright (c) 2015 Rob Morgan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated * documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @package    Phinx
 * @subpackage Phinx\Console
 */

namespace Hail\Database\Console\Migration;

use Hail\Database\Migration\CreationInterface;
use Hail\Database\Migration\Util;

class Create extends AbstractCommand
{
    /**
     * The name of the interface that any external template creation class is required to implement.
     */
    const CREATION_INTERFACE = CreationInterface::class;

    public function brief(): string
    {
        return 'Create a new migration';
    }

    public function help()
    {
        return sprintf(
            '%sCreates a new database migration%s',
            PHP_EOL,
            PHP_EOL
        );
    }

    public function init()
    {
        $this->addArgument('name', 'What is the name of the migration (in CamelCase)?');

        $this->addOption('t|template:', 'Use an alternative template');
        $this->addOption('l|class:',
            'Use a class implementing "' . self::CREATION_INTERFACE . '" to generate the template');
        $this->addOption('path?', 'Specify the path in which to create this migration');
    }

    /**
     * Returns the migration path to create the migration in.
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getMigrationPath()
    {
        // First, try the non-interactive option:
        $path = $this->getOption('path');

        if (!empty($path)) {
            return $path;
        }

        $paths = $this->getConfig()->getMigrationPaths();

        // No paths? That's a problem.
        if (empty($paths)) {
            throw new \InvalidArgumentException('No migration paths set in your migrate configuration file.');
        }

        $paths = Util::globAll($paths);

        if (empty($paths)) {
            throw new \InvalidArgumentException(
                'You probably used curly braces to define migration path in your migrate configuration file, ' .
                'but no directories have been matched using this pattern. ' .
                'You need to create a migration directory manually.'
            );
        }

        // Only one path set, so select that:
        if (1 === \count($paths)) {
            return \current($paths);
        }

        // Ask the user which of their defined paths they'd like to use:
        return $this->getPrompter()->choose('Which migrations path would you like to use?', $paths);
    }

    /**
     * Create the new migration.
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute()
    {
        $this->bootstrap();

        // get the migration path from the config
        $path = $this->getMigrationPath();

        if (!file_exists($path) && $this->getPrompter()->confirm('Create migrations directory?', true)) {
            mkdir($path, 0755, true);
        }

        $this->verifyMigrationDirectory($path);

        $config = $this->getConfig();
        $namespace = $config->getMigrationNamespaceByPath($path);

        $path = realpath($path);
        $className = $this->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s" is invalid. Please use CamelCase format.',
                $className
            ));
        }

        if (!Util::isUniqueMigrationClassName($className, $path)) {
            throw new \InvalidArgumentException(sprintf(
                'The migration class name "%s%s" already exists',
                $namespace ? ($namespace . '\\') : '',
                $className
            ));
        }

        // Compute the file path
        $fileName = Util::mapClassNameToFileName($className);
        $filePath = $path . DIRECTORY_SEPARATOR . $fileName;

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                $filePath
            ));
        }

        // Get the alternative template and static class options from the config, but only allow one of them.
        $defaultAltTemplate = $this->getConfig()->getTemplateFile();
        $defaultCreationClassName = $this->getConfig()->getTemplateClass();
        if ($defaultAltTemplate && $defaultCreationClassName) {
            throw new \InvalidArgumentException('Cannot define template:class and template:file at the same time');
        }

        // Get the alternative template and static class options from the command line, but only allow one of them.
        $altTemplate = $this->getOption('template');
        $creationClassName = $this->getOption('class');
        if ($altTemplate && $creationClassName) {
            throw new \InvalidArgumentException('Cannot use --template and --class at the same time');
        }

        // If no commandline options then use the defaults.
        if (!$altTemplate && !$creationClassName) {
            $altTemplate = $defaultAltTemplate;
            $creationClassName = $defaultCreationClassName;
        }

        // Verify the alternative template file's existence.
        if ($altTemplate && !is_file($altTemplate)) {
            throw new \InvalidArgumentException(sprintf(
                'The alternative template file "%s" does not exist',
                $altTemplate
            ));
        }

        // Verify that the template creation class (or the aliased class) exists and that it implements the required interface.
        $aliasedClassName = null;
        if ($creationClassName) {
            // Supplied class does not exist, is it aliased?
            if (!class_exists($creationClassName)) {
                $aliasedClassName = $this->getConfig()->getAlias($creationClassName);
                if ($aliasedClassName && !class_exists($aliasedClassName)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The class "%s" via the alias "%s" does not exist',
                        $aliasedClassName,
                        $creationClassName
                    ));
                }

                if (!$aliasedClassName) {
                    throw new \InvalidArgumentException(sprintf(
                        'The class "%s" does not exist',
                        $creationClassName
                    ));
                }
            }

            // Does the class implement the required interface?
            if (!$aliasedClassName && !is_subclass_of($creationClassName, self::CREATION_INTERFACE)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" does not implement the required interface "%s"',
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            }

            if ($aliasedClassName && !is_subclass_of($aliasedClassName, self::CREATION_INTERFACE)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" via the alias "%s" does not implement the required interface "%s"',
                    $aliasedClassName,
                    $creationClassName,
                    self::CREATION_INTERFACE
                ));
            }
        }

        // Use the aliased class.
        $creationClassName = $aliasedClassName ?: $creationClassName;

        // Determine the appropriate mechanism to get the template
        if ($creationClassName) {
            // Get the template from the creation class
            /** @var CreationInterface $creationClass */
            $creationClass = new $creationClassName($this);
            $contents = $creationClass->getMigrationTemplate();
        } else {
            // Load the alternative template if it is defined.
            $contents = file_get_contents($altTemplate ?: $this->getMigrationTemplateFilename());
        }

        // inject the class names appropriate to this migration
        $classes = [
            '$namespaceDefinition' => null !== $namespace ? ('namespace ' . $namespace . ';') : '',
            '$namespace' => $namespace,
            '$useClassName' => $this->getConfig()->getMigrationBaseClassName(false),
            '$className' => $className,
            '$version' => Util::getVersionFromFileName($fileName),
            '$baseClassName' => $this->getConfig()->getMigrationBaseClassName(true),
        ];
        $contents = strtr($contents, $classes);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        // Do we need to do the post creation call to the creation class?
        if (isset($creationClass)) {
            $creationClass->postMigrationCreation($filePath, $className,
                $this->getConfig()->getMigrationBaseClassName());
        }

        $output = $this->getOutput();
        $output->write('using migration base class ', 'info');
        $output->writeln($classes['$useClassName']);

        if (!empty($altTemplate)) {
            $output->write('using alternative template ', 'info');
            $output->writeln($altTemplate);
        } elseif (!empty($creationClassName)) {
            $output->write('using template creation class ', 'info');
            $output->writeln($creationClassName);
        } else {
            $output->writeln('using default template', 'info');
        }

        $output->write('created ', 'info');
        $output->writeln(str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $filePath));
    }
}
