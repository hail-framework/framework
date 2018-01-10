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

use Hail\Database\Migration\Util;
use Hail\Database\Migration\Seed\AbstractSeed;

class SeedCreate extends AbstractCommand
{
    public function brief(): string
    {
        return 'Create a new database seeder';
    }

    public function help()
    {
        return sprintf(
            '%sCreates a new database seeder%s',
            PHP_EOL,
            PHP_EOL
        );
    }

    public function init()
    {
        $this->addArgument('name', 'What is the name of the seeder?');

        $this->addOption('path?', 'Specify the path in which to create this seeder');
    }

    /**
     * Returns the seed path to create the seeder in.
     *
     * @return mixed
     * @throws \Exception
     */
    protected function getSeedPath()
    {
        // First, try the non-interactive option:
        $path = $this->getOption('path');

        if (!empty($path)) {
            return $path;
        }

        $paths = $this->getConfig()->getSeedPaths();

        // No paths? That's a problem.
        if (empty($paths)) {
            throw new \Exception('No seed paths set in your Phinx configuration file.');
        }

        $paths = Util::globAll($paths);

        if (empty($paths)) {
            throw new \Exception(
                'You probably used curly braces to define seed path in your Phinx configuration file, ' .
                'but no directories have been matched using this pattern. ' .
                'You need to create a seed directory manually.'
            );
        }

        // Only one path set, so select that:
        if (1 === \count($paths)) {
            return \current($paths);
        }

        // Ask the user which of their defined paths they'd like to use:
        return $this->getPrompter()->choose('Which seeds path would you like to use?', $paths);
    }

    /**
     * Create the new seeder.
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute()
    {
        $this->bootstrap();

        // get the seed path from the config
        $path = $this->getSeedPath();

        if (!file_exists($path) && $this->getPrompter()->confirm('Create seeds directory?', true)) {
            mkdir($path, 0755, true);
        }

        $this->verifySeedDirectory($path);

        $path = realpath($path);
        $className = $this->getArgument('name');

        if (!Util::isValidPhinxClassName($className)) {
            throw new \InvalidArgumentException(sprintf(
                'The seed class name "%s" is invalid. Please use CamelCase format',
                $className
            ));
        }

        // Compute the file path
        $filePath = $path . DIRECTORY_SEPARATOR . $className . '.php';

        if (is_file($filePath)) {
            throw new \InvalidArgumentException(sprintf(
                'The file "%s" already exists',
                basename($filePath)
            ));
        }

        // inject the class names appropriate to this seeder
        $contents = file_get_contents($this->getSeedTemplateFilename());

        $config = $this->getConfig();
        $namespace = $config->getSeedNamespaceByPath($path);

        $classes = [
            '$namespaceDefinition' => null !== $namespace ? ('namespace ' . $namespace . ';') : '',
            '$namespace' => $namespace,
            '$useClassName' => AbstractSeed::class,
            '$className' => $className,
            '$baseClassName' => 'AbstractSeed',
        ];
        $contents = strtr($contents, $classes);

        if (false === file_put_contents($filePath, $contents)) {
            throw new \RuntimeException(sprintf(
                'The file "%s" could not be written to',
                $path
            ));
        }

        $output = $this->getOutput();
        $output->writeln('<info>using seed base class</info> ' . $classes['$useClassName']);
        $output->writeln('<info>created</info> .' . str_replace(getcwd(), '', $filePath));
    }
}
