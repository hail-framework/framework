<?php
namespace Hail\Factory;

use Hail\Facade\Config;
use Hail\Util\Serialize;

abstract class AbstractFactory
{
	protected static $pool = [];

	public static function get($type, array $config = [])
	{
		return static::$type($config);
	}

	protected static function getKey(array $config, $default)
    {
        $prefix = static::class . '|';
        $defaultConfig = Config::get($default);

        if ($config === [] || $config === $defaultConfig) {
            return [$prefix . 'default', $defaultConfig];
        }

        return [$prefix . \Serialize::encode($config), $config + $defaultConfig];
    }
}