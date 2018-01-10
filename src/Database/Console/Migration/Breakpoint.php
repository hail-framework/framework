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
 * @author     Richard Quadling
 * @package    Phinx
 * @subpackage Phinx\Console
 */

namespace Hail\Database\Console\Migration;

class Breakpoint extends AbstractCommand
{
    public function brief(): string
    {
        return 'Manage breakpoints';
    }

    public function help()
    {
        return <<<EOT
The <info>breakpoint</info> command allows you to set or clear a breakpoint against a specific target to inhibit rollbacks beyond a certain target.
If no target is supplied then the most recent migration will be used.
You cannot specify un-migrated targets

<info>migration breakpoint -e development</info>
<info>migration breakpoint -e development -t 20110103081132</info>
<info>migration breakpoint -e development -r</info>
EOT;
    }

    public function init()
    {
        $this->addOption('e|environment?', 'The target environment');
        $this->addOption('t|target?=int', 'The version number to migrate to');
        $this->addOption('r|remove-all', 'Remove all breakpoints');
    }

    /**
     * Toggle the breakpoint.
     *
     * @return void
     */
    protected function execute()
    {
        $this->bootstrap();

        $environment = $this->getOption('environment');
        $version = $this->getOption('target');
        $removeAll = $this->getOption('remove-all');

        $output = $this->getOutput();

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        if ($version && $removeAll) {
            throw new \InvalidArgumentException('Cannot toggle a breakpoint and remove all breakpoints at the same time.');
        }

        // Remove all breakpoints
        if ($removeAll) {
            $this->getManager()->removeBreakpoints($environment);
        } else {
            // Toggle the breakpoint.
            $this->getManager()->toggleBreakpoint($environment, $version);
        }
    }
}
