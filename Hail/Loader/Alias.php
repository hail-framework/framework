<?php
/**
 * @from https://github.com/laravel/framework/blob/5.1/src/Illuminate/Foundation/AliasLoader.php
 * Copyright (c) <Taylor Otwell> Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Loader;

/**
 * Class Alias
 * @package Hail\Loader
 */
class Alias
{
    /**
     * The array of class aliases.
     *
     * @var array
     */
    protected $aliases;

    /**
     * Create a new AliasLoader instance.
     *
     * @param  array  $aliases
     */
    public function __construct($aliases)
    {
        $this->aliases = $aliases;
    }

    /**
     * Load a class alias if it is registered.
     *
     * @param  string  $alias
     * @return bool | null
     */
    public function load($alias)
    {
        if (isset($this->aliases[$alias])) {
            return class_alias($this->aliases[$alias], $alias);
        }
    }

    /**
     * Add an alias to the loader.
     *
     * @param  string  $class
     * @param  string  $alias
     * @return void
     */
    public function alias($class, $alias)
    {
        $this->aliases[$class] = $alias;
    }

    /**
     * Prepend the loader to the auto-loader stack.
     *
     * @return void
     */
    public function register()
    {
        spl_autoload_register([$this, 'load'], true, true);
    }
}
