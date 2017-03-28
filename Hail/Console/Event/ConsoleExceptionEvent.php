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
 * Allows to handle exception thrown in a command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class ConsoleExceptionEvent extends ConsoleEvent
{
    /**
     * Returns the thrown exception.
     *
     * @return \Exception The thrown exception
     */
    public function getException()
    {
        return $this->getParam('exception');
    }

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
