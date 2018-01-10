<?php

namespace Hail\Database\Console\Migration;


class Migrate extends AbstractCommand
{
    public function brief(): string
    {
        return 'Migrate the database';
    }

    public function help()
    {
        return <<<EOT
The <info>migrate</info> command runs all available migrations, optionally up to a specific version

<info>migration migrate -e development</info>
<info>migration migrate -e development -t 20110103081132</info>
<info>migration migrate -e development -d 20110103</info>
<info>migration migrate -e development -v</info>

EOT;
    }

    public function init()
    {
        $this->addOption('e|environment?', 'The target environment');
        $this->addOption('t|target?=int', 'The version number to migrate to');
        $this->addOption('d|date?=date', 'The date to migrate to');
        $this->addOption('x|dry-run', 'Dump query to standard output instead of executing it');
    }

    public function execute()
    {
        $this->bootstrap();

        $output = $this->logger;

        $version = $this->getOption('target');
        $environment = $this->getOption('environment');
        $date = $this->getOption('date');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->write('warning', 'comment');
            $output->write(' no environment specified, defaulting to: ');
        } else {
            $output->write('using environment ', 'info');
        }
        $output->writeln($environment);

        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter'])) {
            $output->write('using adapter ', 'info');
            $output->writeln($envOptions['adapter']);
        }

        if (isset($envOptions['wrapper'])) {
            $output->write('using wrapper ', 'info');
            $output->writeln($envOptions['wrapper']);
        }

        if (isset($envOptions['name'])) {
            $output->write('using database ', 'info');
            $output->writeln($envOptions['name']);
        } else {
            $output->writeln('Could not determine database name! Please specify a database name in your config file.',
                'error');

            return;
        }

        if (isset($envOptions['table_prefix'])) {
            $output->write('using table prefix ', 'info');
            $output->writeln($envOptions['table_prefix']);
        }
        if (isset($envOptions['table_suffix'])) {
            $output->write('using table suffix ', 'info');
            $output->writeln($envOptions['table_suffix']);
        }

        // run the migrations
        $start = microtime(true);
        if ($date instanceof \DateTime) {
            $this->getManager()->migrateToDateTime($environment, $date);
        } else {
            $this->getManager()->migrate($environment, $version);
        }
        $end = microtime(true);

        $output->newline();
        $output->writeln('All Done. Took ' . sprintf('%.4fs', $end - $start), 'comment');
    }
}
