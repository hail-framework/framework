<?php
namespace Hail\Factory;

abstract class AbstractFactory
{
	protected static $pool = [];

	public static function get($type, array $config = [])
	{
		return static::$type($config);
	}
}