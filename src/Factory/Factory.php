<?php
namespace Hail\Factory;

use Hail\Facade\Config;
use Hail\Util\Serialize;

abstract class Factory
{
	protected static $pool = [];

	public static function get($type, array $config = [])
	{
		return static::$type($config);
	}

	protected static function getKey(array $config, $default)
    {
        $defaultConfig = Config::get($default);

        if ($config === [] || $config === $defaultConfig) {
            return ['default', $defaultConfig];
        }

        return [Serialize::encode($config), $config + $defaultConfig];
    }
}