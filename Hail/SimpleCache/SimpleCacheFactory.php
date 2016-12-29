<?php

namespace Hail\SimpleCache;

use Hail\SimpleCache\Exception\InvalidArgumentException;

class SimpleCacheFactory
{
	public static function get($config)
	{
		if (isset($config['drivers'])) {
			$drivers = $config['drivers'];
			if (!is_array($drivers)) {
				throw new InvalidArgumentException('SimpleCache config "drivers" must be the array');
			}

			if (count($drivers) > 1) {
				return new Adapter\Chain($config);
			}

			unset($config['drivers']);

			$driver = key($drivers);
			$config += $drivers[$driver];
		} else {
			if (!isset($config['driver'])) {
				throw new InvalidArgumentException('SimpleCache config "driver" not defined');
			}

			$driver = $config['driver'];
			unset($config['driver']);
		}

		$class = self::getAdapter($driver);

		return new $class($config);
	}

	public static function getAdapter($driver)
	{
		switch ($driver) {
			case 'array':
			case 'zend':
				$driver = ucfirst($driver) . 'Data';
				break;
			case 'apc':
				$driver = 'Apcu';
				break;
			default:
				$driver = ucfirst($driver);
		}

		return __NAMESPACE__ . '\\Adapter\\' . $driver;
	}
}