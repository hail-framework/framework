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
 * Allows to manipulate the exit code of a command after its execution.
 *
 * @author Francesco Levorato <git@flevour.net>
 * @author Hao Feng <flyinghail@msn.com>
 */
class ConsoleTerminateEvent extends ConsoleEvent
{
    /**
     * Gets the exit code.
     *
     * @return int The command exit code
     */
    public function getExitCode()
    {
        return $this->getParam('exitCode');
    }
}
