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

use Hail\Console\Command;
use Hail\Database\Migration\Config;
use Hail\Database\Migration\Manager;
use Hail\Database\Migration\Adapter\AdapterInterface;
use Hail\Database\Migration\Util;

/**
 * Abstract command, contains bootstrapping info
 *
 * @author Rob Morgan <robbym@gmail.com>
 */
abstract class AbstractCommand extends Command
{
    /**
     * The location of the default migration template.
     */
    const DEFAULT_MIGRATION_TEMPLATE = __DIR__ . '/../Migration/Migration.template.php.dist';

    /**
     * The location of the default seed template.
     */
    const DEFAULT_SEED_TEMPLATE = __DIR__ . '/../Seed/Seed.template.php.dist';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var Manager
     */
    protected $manager;

    /**
     * Bootstrap
     *
     * @return void
     */
    public function bootstrap()
    {
        if (!$this->getConfig()) {
            $this->loadConfig();
        }

        $output = $this->getOutput();

        $this->loadManager();

        // report the paths
        $paths = $this->getConfig()->getMigrationPaths();

        $output->writeln('using migration paths', 'info');

        foreach (Util::globAll($paths) as $path) {
            $output->writeln(' - ' . realpath($path), 'info');
        }

        try {
            $paths = $this->getConfig()->getSeedPaths();

            $output->writeln('using seed paths', 'info');

            foreach (Util::globAll($paths) as $path) {
                $output->writeln(' - ' . realpath($path), 'info');
            }
        } catch (\UnexpectedValueException $e) {
            // do nothing as seeds are optional
        }
    }

    /**
     * Sets the config.
     *
     * @param  Config $config
     *
     * @return AbstractCommand
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Gets the config.
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Sets the database adapter.
     *
     * @param AdapterInterface $adapter
     *
     * @return AbstractCommand
     */
    public function setAdapter(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * Gets the database adapter.
     *
     * @return AdapterInterface
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Sets the migration manager.
     *
     * @param Manager $manager
     *
     * @return AbstractCommand
     */
    public function setManager(Manager $manager)
    {
        $this->manager = $manager;

        return $this;
    }

    /**
     * Gets the migration manager.
     *
     * @return Manager
     */
    public function getManager(): ?Manager
    {
        return $this->manager;
    }

    /**
     * Load the migrations manager and inject the config
     *
     */
    protected function loadManager()
    {
        if (null === $this->getManager()) {
            $manager = new Manager($this->getConfig(), $this);
            $this->setManager($manager);
        } else {
            $manager = $this->getManager();
            $manager->setCommand($this);
        }
    }

    /**
     * Verify that the migration directory exists and is writable.
     *
     * @param string $path
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function verifyMigrationDirectory($path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Migration directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Migration directory "%s" is not writable',
                $path
            ));
        }
    }

    /**
     * Verify that the seed directory exists and is writable.
     *
     * @param string $path
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function verifySeedDirectory($path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Seed directory "%s" does not exist',
                $path
            ));
        }

        if (!is_writable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'Seed directory "%s" is not writable',
                $path
            ));
        }
    }

    /**
     * Returns the migration template filename.
     *
     * @return string
     */
    protected function getMigrationTemplateFilename()
    {
        return self::DEFAULT_MIGRATION_TEMPLATE;
    }

    /**
     * Returns the seed template filename.
     *
     * @return string
     */
    protected function getSeedTemplateFilename()
    {
        return self::DEFAULT_SEED_TEMPLATE;
    }

    /**
     * Parse the config file and load it into the config object
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function loadConfig()
    {
        $config = new Config(
            \Hail\Facade\Config::get('migrate')
        );

        $this->setConfig($config);
    }
}
