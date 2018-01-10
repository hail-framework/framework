<?php

/*
 * This file is part of the CLIFramework package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console\Command;

use Hail\Console\Command;
use Hail\Console\Completion\ZshGenerator;
use Hail\Console\Option\OptionCollection;

class ZshCompletion extends Command
{
    public function name(): string
    {
        return 'zsh';
    }

    public function brief()
    {
        return 'This function generate a zsh-completion script automatically';
    }

    public function init()
    {
        $this->addOption('bind:', 'bind complete to command');
        $this->addOption('program:', 'programe name');
    }

    public function execute()
    {
        $programName = $this->getOption('program') ?: $this->getApplication()->getProgramName();
        $bind = $this->getOption('bind') ?: $programName;
        $compName = '_'.preg_replace('#\W+#', '_', $programName);
        $generator = new ZshGenerator($this->getApplication(), $programName, $bind, $compName);
        echo $generator->output();
    }
}
