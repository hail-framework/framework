<?php

namespace Hail\Facade;

/**
 * Class Facade
 *
 * @package Hail\Facade
 * @author  Hao Feng <flyinghail@msn.com>
 */
abstract class DynamicFacade extends Facade
{
	/**
	 * Get the root object behind the facade.
	 *
	 * @return mixed
	 */
	public static function getInstance()
	{
		if (!isset(static::$instances[static::class])) {
			static::$instances[static::class] =
				static::$container->create(static::$name);
		}

		return static::$instances[static::class];
	}
}