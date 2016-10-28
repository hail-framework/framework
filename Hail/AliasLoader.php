<?php
/**
 * @from https://github.com/laravel/framework/blob/5.3/src/Illuminate/Foundation/AliasLoader.php
 * Copyright (c) <Taylor Otwell> Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail;

/**
 * Class AliasLoader
 *
 * @package Hail
 */
class AliasLoader
{
	/**
	 * The array of class aliases.
	 *
	 * @var array
	 */
	protected $aliases;

	/**
	 * Indicates if a loader has been registered.
	 *
	 * @var bool
	 */
	protected $registered = false;

	/**
	 * Create a new AliasLoader instance.
	 *
	 * @param  array $aliases
	 */
	public function __construct($aliases)
	{
		$this->aliases = $aliases;
	}

	/**
	 * Load a class alias if it is registered.
	 *
	 * @param  string $alias
	 *
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
	 * @param  string $class
	 * @param  string $alias
	 *
	 * @return void
	 */
	public function alias($class, $alias)
	{
		$this->aliases[$class] = $alias;
	}

	/**
	 * Get the registered aliases.
	 *
	 * @return array
	 */
	public function getAliases()
	{
		return $this->aliases;
	}

	/**
	 * Set the registered aliases.
	 *
	 * @param  array  $aliases
	 * @return void
	 */
	public function setAliases(array $aliases)
	{
		$this->aliases = $aliases;
	}

	/**
	 * Prepend the loader to the auto-loader stack.
	 *
	 * @return void
	 */
	public function register()
	{
		if (!$this->registered) {
			spl_autoload_register([$this, 'load'], true, true);
			$this->registered = true;
		}
	}
}
