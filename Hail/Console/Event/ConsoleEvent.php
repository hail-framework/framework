<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Console\Event;

use Hail\Console\Command\Command;
use Hail\Console\Input\InputInterface;
use Hail\Console\Output\OutputInterface;
use Hail\Event\Event;

/**
 * Allows to inspect input and output of a command.
 *
 * @author Francesco Levorato <git@flevour.net>
 * @author Hao Feng <flyinghail@msn.com>
 */
class ConsoleEvent extends Event
{
    /**
     * Gets the command that is executed.
     *
     * @return Command A Command instance
     */
    public function getCommand(): Command
    {
        return $this->getParam('command');
    }

    /**
     * Gets the input instance.
     *
     * @return InputInterface An InputInterface instance
     */
    public function getInput(): InputInterface
    {
        return $this->getParam('input');
    }

    /**
     * Gets the output instance.
     *
     * @return OutputInterface An OutputInterface instance
     */
    public function getOutput(): OutputInterface
    {
        return $this->getParam('output');
    }
}
