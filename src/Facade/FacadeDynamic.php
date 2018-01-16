<?php

namespace Hail\Facade;

/**
 * Class Facade
 *
 * @package Hail\Facade
 * @author  Feng Hao <flyinghail@msn.com>
 */
abstract class FacadeDynamic extends Facade
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
                static::$container->build(static::class);
		}

		return static::$instances[static::class];
	}
}