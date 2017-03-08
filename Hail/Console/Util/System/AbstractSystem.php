<?php

namespace Hail\Console\Util\System;

use Hail\Util\SingletonTrait;

abstract class AbstractSystem
{
	use SingletonTrait;

    protected $forceAnsi;

    /**
     * Force ansi on or off
     *
     * @param bool $force
     */
    public function forceAnsi($force = true)
    {
        $this->forceAnsi = $force;
    }

    /**
     * @return integer|null
     */
    abstract public function width();

    /**
     * @return integer|null
     */
    abstract public function height();

    /**
     * Check if the stream supports ansi escape characters.
     *
     * @return bool
     */
    abstract protected function systemHasAnsiSupport();

    /**
     * Check if we are forcing ansi, fallback to system support
     *
     * @return bool
     */
    public function hasAnsiSupport()
    {
        if (is_bool($this->forceAnsi)) {
            return $this->forceAnsi;
        }

        return $this->systemHasAnsiSupport();
    }

    /**
     * Wraps exec function, allowing the dimension methods to decouple
     *
     * @param string $command
     * @param boolean $full
     *
     * @return string|array
     */
    public function exec($command, $full = false)
    {
        if ($full) {
            exec($command, $output);

            return $output;
        }

        return exec($command);
    }
}
