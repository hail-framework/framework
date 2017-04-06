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

use Hail\Console\Exception\InvalidArgumentException;
/**
 * Allows to handle throwables thrown while running a command.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class ConsoleErrorEvent extends ConsoleEvent
{
    /**
     * Returns the thrown error/exception.
     *
     * @return \Throwable
     */
    public function getError()
    {
        return $this->getParam('error');
    }

    /**
     * Replaces the thrown error/exception.
     *
     * @param \Throwable $error
     */
    public function setError($error)
    {
        if (!$error instanceof \Throwable && !$error instanceof \Exception) {
            throw new InvalidArgumentException(sprintf('The error passed to ConsoleErrorEvent must be an instance of \Throwable or \Exception, "%s" was passed instead.', is_object($error) ? get_class($error) : gettype($error)));
        }

        $this->setParam('error', $error);
    }

    /**
     * Marks the error/exception as handled.
     *
     * If it is not marked as handled, the error/exception will be displayed in
     * the command output.
     */
    public function markErrorAsHandled()
    {
        $this->setParam('handled', true);
    }

    /**
     * Whether the error/exception is handled by a listener or not.
     *
     * If it is not yet handled, the error/exception will be displayed in the
     * command output.
     *
     * @return bool
     */
    public function isErrorHandled()
    {
        return $this->getParam('handled') ?? false;
    }
}
