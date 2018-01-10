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

class Rollback extends AbstractCommand
{
    public function brief(): string
    {
        return 'Rollback the last or to a specific migration';
    }

    public function help()
    {
        return <<<EOT
The <info>rollback</info> command reverts the last migration, or optionally up to a specific version

<info>migration rollback -e development</info>
<info>migration rollback -e development -t 20111018185412</info>
<info>migration rollback -e development -d 20111018</info>
<info>migration rollback -e development -v</info>
<info>migration rollback -e development -t 20111018185412 -f</info>

If you have a breakpoint set, then you can rollback to target 0 and the rollbacks will stop at the breakpoint.
<info>migrate rollback -e development -t 0 </info>

The <info>version_order</info> configuration option is used to determine the order of the migrations when rolling back.
This can be used to allow the rolling back of the last executed migration instead of the last created one, or combined 
with the <info>-d|--date</info> option to rollback to a certain date using the migration start times to order them.

EOT;
    }

    public function init()
    {
        $this->addOption('e|environment?', 'The target environment');
        $this->addOption('t|target?=int', 'The version number to migrate to');
        $this->addOption('d|date?=date', 'The date to migrate to');
        $this->addOption('f|force', 'Force rollback to ignore breakpoints');
        $this->addOption('x|dry-run', 'Dump query to standard output instead of executing it');
    }

    /**
     * Rollback the migration.
     *
     * @return void
     */
    protected function execute()
    {
        $this->bootstrap();

        $environment = $this->getOption('environment');
        $version = $this->getOption('target');
        $date = $this->getOption('date');
        $force = (bool) $this->getOption('force');
        $output = $this->getOutput();

        $config = $this->getConfig();

        if (null === $environment) {
            $environment = $config->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $config->getEnvironment($environment);
        if (isset($envOptions['adapter'])) {
            $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        }

        if (isset($envOptions['wrapper'])) {
            $output->writeln('<info>using wrapper</info> ' . $envOptions['wrapper']);
        }

        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        }

        $versionOrder = $this->getConfig()->getVersionOrder();
        $output->writeln('<info>ordering by </info>' . $versionOrder . " time");

        // rollback the specified environment
        if (null === $date) {
            $targetMustMatchVersion = true;
            $target = $version;
        } else {
            $targetMustMatchVersion = false;
            $target = $date->format('YmdHis');
        }

        $start = microtime(true);
        $this->getManager()->rollback($environment, $target, $force, $targetMustMatchVersion);
        $end = microtime(true);

        $output->newline();
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }
}
