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

/**
 * Allows to do things before the command is executed, like skipping the command or changing the input.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class ConsoleCommandEvent extends ConsoleEvent
{
    /**
     * The return code for skipped commands, this will also be passed into the terminate event.
     */
    const RETURN_CODE_DISABLED = 113;

    /**
     * Disables the command, so it won't be run.
     *
     * @return bool
     */
    public function disableCommand()
    {
        return $this->setParam('commandShouldRun', false);
    }

    /**
     * Enables the command.
     *
     * @return bool
     */
    public function enableCommand()
    {
	    return $this->setParam('commandShouldRun', true);
    }

    /**
     * Returns true if the command is runnable, false otherwise.
     *
     * @return bool
     */
    public function commandShouldRun()
    {
        return $this->getParam('commandShouldRun');
    }
}
