<?php

namespace Hail\Database\Console\Migration;

use PDO;
use Exception;
use Hail\Database\Migration\Generator;
use Hail\Database\Migration\Adapter\PdoAdapter;
use Hail\Database\Migration\Manager;

class Generate extends AbstractCommand
{
    public function brief()
    {
        return 'Generate a new migration';
    }

    public function init()
    {
        // Allow the migration path to be chosen non-interactively.
        $this->addOption('path:', 'Specify the path in which to generate this migration');
        $this->addOption('name:', 'Specify the name of the migration for this migration');
        $this->addOption('overwrite', 'Overwrite schema.php file');
    }

    /**
     * Generate migration.
     *
     * @throws Exception On Error
     */
    protected function execute()
    {
        $this->bootstrap();

        $output = $this->getOutput();
        $environment = $this->getOption('environment');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }
        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter']) && $envOptions['adapter'] !== 'mysql') {
            $output->writeln('<error>adapter not supported</error> ' . $envOptions['adapter']);

            return;
        }

        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        } else {
            $output->writeln('<error>Could not determine database name! Please specify a database name in your config file.</error>');

            return;
        }

        // Load config and database adapter
        $manager = $this->getManager();
        $config = $manager->getConfig();

        $configFilePath = $config->getConfigFilePath();
        $output->writeln('<info>using config file</info> ' . $configFilePath);

        // First, try the non-interactive option:
        $migrationsPaths = (array) $this->getOption('path');
        if (empty($migrationsPaths)) {
            $migrationsPaths = $config->getMigrationPaths();
        }
        // No paths? That's a problem.
        if (empty($migrationsPaths)) {
            throw new \Exception('No migration paths set in your Phinx configuration file.');
        }

        $migrationsPath = $migrationsPaths[0];
        $this->verifyMigrationDirectory($migrationsPath);

        $output->writeln('<info>using migration path</info> ' . $migrationsPath);

        $schemaFile = $migrationsPath . DIRECTORY_SEPARATOR . 'schema.php';
        $output->writeln('<info>using schema file</info> ' . $schemaFile);

        // Gets the database adapter.
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();

        $pdo = $this->getPdo($manager, $environment);

        $foreignKeys = $config->offsetExists('foreign_keys') ? $config->offsetGet('foreign_keys') : false;
        $defaultMigrationTable = $envOptions['default_migration_table'] ?? 'migrate_log';

        $name = $this->getOption('name');
        $overwrite = $this->getOption('overwrite');

        $settings = [
            'pdo' => $pdo,
            'manager' => $manager,
            'environment' => $environment,
            'adapter' => $dbAdapter,
            'schema_file' => $schemaFile,
            'migration_path' => $migrationsPaths[0],
            'foreign_keys' => $foreignKeys,
            'config_file' => $configFilePath,
            'name' => $name,
            'overwrite' => $overwrite,
            'mark_migration' => true,
            'default_migration_table' => $defaultMigrationTable,
        ];

        $generator = new Generator($settings, $this);

        $generator->generate();
    }

    /**
     * Get PDO instance.
     *
     * @param Manager $manager     Manager
     * @param string  $environment Environment name
     *
     * @return PDO PDO object
     * @throws Exception On error
     */
    protected function getPdo(Manager $manager, $environment)
    {
        // Gets the database adapter.
        $dbAdapter = $manager->getEnvironment($environment)->getAdapter();

        if ($dbAdapter instanceof PdoAdapter) {
            $pdo = $dbAdapter->getConnection();
        } else {
            $dbAdapter->connect();
            $pdo = $dbAdapter->getAdapter()->getConnection();
        }
        if (!$pdo) {
            $pdo = $dbAdapter->getOption('connection');
        }
        if (!$pdo instanceof PDO) {
            throw new Exception('No PDO database connection found.');
        }

        return $pdo;
    }
}
