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

use Hail\Database\Migration\Manager\Environment;
use Hail\Database\Migration\Util;

/**
 * @author Leonid Kuzmin <lndkuzmin@gmail.com>
 */
class Test extends AbstractCommand
{
    public function brief(): string
    {
        return 'Verify the configuration file';
    }

    public function help()
    {
        return <<<EOT
The <info>test</info> command verifies the configuration file and optionally an environment

<info>migration test</info>
<info>migration test -e development</info>

EOT;
    }

    public function init()
    {
        $this->addOption('e|environment?', 'The target environment');
    }

    /**
     * Verify configuration file
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute()
    {
        $this->loadConfig();
        $this->loadManager();

        // Verify the migrations path(s)
        array_map(
            [$this, 'verifyMigrationDirectory'],
            Util::globAll($this->getConfig()->getMigrationPaths())
        );

        // Verify the seed path(s)
        array_map(
            [$this, 'verifySeedDirectory'],
            Util::globAll($this->getConfig()->getSeedPaths())
        );

        $output = $this->getOutput();
        $envName = $this->getOption('environment');
        if ($envName) {
            if (!$this->getConfig()->hasEnvironment($envName)) {
                throw new \InvalidArgumentException(sprintf(
                    'The environment "%s" does not exist',
                    $envName
                ));
            }

            $output->writeln(sprintf('<info>validating environment</info> %s', $envName));
            $environment = new Environment(
                $envName,
                $this->getConfig()->getEnvironment($envName)
            );
            // validate environment connection
            $environment->getAdapter()->connect();
        }

        $output->writeln('<info>success!</info>');
    }
}
