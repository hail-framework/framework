<?php

namespace Hail\Util;

/**
 * Class Singleton
 * Defines a class as a singleton.
 *
 * @package Hail\Util
 */
trait Singleton
{
	protected static $instance;

	/**
	 * @return static
	 */
	final public static function getInstance()
	{
		return static::$instance ?? (static::$instance = new static);
	}

	/**
	 * Singleton constructor.
	 */
	final protected function __construct()
	{
		$this->init();
	}

	/**
	 * Initializes the singleton
	 *
	 * @return void
	 */
	protected function init()
	{
	}

	final public function __wakeup()
	{
	}

	final private function __clone()
	{
	}
}
