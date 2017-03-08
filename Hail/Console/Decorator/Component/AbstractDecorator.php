<?php

namespace Hail\Console\Decorator\Component;

abstract class AbstractDecorator
{
    /**
     * An array of defaults for the decorator
     *
     * @var array $defaults;
     */
    protected $defaults = [];

    /**
     * An array of currently set codes for the decorator
     *
     * @var array $current;
     */
    protected $current  = [];

    /**
     * Load up the defaults for this decorator
     */
    public function defaults()
    {
        foreach ($this->defaults as $name => $code) {
            $this->add($name, $code);
        }
    }

    /**
     * Reset the currently set decorator
     */
    public function reset()
    {
        $this->current = [];
    }

    /**
     * Retrieve the currently set codes for the decorator
     *
     * @return array
     */
    public function current()
    {
        return $this->current;
    }

	abstract public function add($key, $value);

	abstract public function get($val);

	abstract public function set($val);

	abstract public function all();
}
