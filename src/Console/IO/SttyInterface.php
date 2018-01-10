<?php

namespace Hail\Console\IO;

/**
 * The interface of classes which handle stty.
 */
interface SttyInterface
{
    /**
     * Turn on echo.
     */
    public function enableEcho();

    /**
     * Turn off echo.
     */
    public function disableEcho();

    /**
     * Dump all current settings in a-stty readable form.
     *
     * @return string
     */
    public function dump();

    /**
     * Turn off echoing and execute the callback function.
     *
     * @param \Closure $callback
     *
     * @return mixed
     */
    public function withoutEcho(\Closure $callback);
}
