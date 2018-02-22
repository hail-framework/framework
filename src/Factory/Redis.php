<?php
namespace Hail\Factory;

use Hail\Redis\Factory as RedisFactory;
use Hail\Redis\RedisInterface;

class Redis extends AbstractFactory
{
	/**
	 * @param array $config
	 *
	 * @return RedisInterface
	 */
	public static function client(array $config = []): RedisInterface
	{
		[$hash, $config] = static::getKey($config, 'redis.client');

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

        return static::$pool[$hash] = RedisFactory::client($config);
	}

	public static function cluster(array $config = []): RedisInterface
    {
        [$hash, $config] = static::getKey($config, 'redis.cluster');

        if (isset(static::$pool[$hash])) {
            return static::$pool[$hash];
        }

        return static::$pool[$hash] = RedisFactory::cluster($config);
    }
}