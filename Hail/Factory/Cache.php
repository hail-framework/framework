<?php
namespace Hail\Factory;

use Hail\Cache\{
	RedisCachePool,
	SimpleCachePool
};
use Hail\SimpleCache\{
	CacheInterface,
	Chain
};
use Hail\Facade\{
	Config, Serialize
};


class Cache extends Factory
{
	/**
	 * @param array $config
	 *
	 * @return CacheInterface
	 */
	public static function simple(array $config = []): CacheInterface
	{
		$config += Config::get('cache.simple');
		$hash = sha1(Serialize::encode($config));

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

		if (isset($config['drivers'])) {
			$drivers = $config['drivers'];
			if (count($drivers) > 1) {
				return static::$pool[$hash] = new Chain($config);
			}

			unset($config['drivers']);

			$driver = key($drivers);
			$config += $drivers[$driver];
		} else {
			$driver = $config['driver'] ?? 'void';
			unset($config['driver']);
		}

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

		$class = 'Hail\\SimpleCache\\' . $driver;

		return static::$pool[$hash] = new $class($config);
	}

	public static function pool(array $config = [])
	{
		$config += Config::get('cache.pool');
		$hash = sha1(Serialize::encode($config));

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

		switch (strtolower($config['driver'])) {
			case 'redis':
				$config = $config['config'] ?? [];
				$namespace = $config['namespace'] ?? '';
				unset($config['namespace']);

				$redis = Redis::client($config);

				return static::$pool[$hash] = new RedisCachePool($redis, $namespace);

			case 'simple':
			case 'simplecache':
				$cache = static::simple($config['config']);

				return static::$pool[$hash] = new SimpleCachePool($cache);

			default:
				throw new \LogicException("PSR6 cache adapter {$config['adapter']} not defined! ");
		}
	}
}
