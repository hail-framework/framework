<?php
namespace Hail\Factory;

use Hail\Redis\Client\{
	AbstractClient,
	Native,
	ConnectPool,
	PhpRedis
};
use Hail\Redis\Exception\RedisException;
use Hail\Facades\{
	Config,
	Serialize
};

class Redis extends Factory
{
	protected static $extension;
	protected static $connectPool;

	/**
	 * @param array $config
	 *
	 * @return AbstractClient
	 * @throws RedisException
	 */
	public static function client(array $config = []): AbstractClient
	{
		if (static::$pool === []) {
			static::$extension = extension_loaded('redis');
			static::$connectPool = class_exists('\redisProxy', false);
		}

		$config += Config::get('redis');
		$hash = sha1(Serialize::encode($config));

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

		$driver = $config['driver'] ?? '';

		if ($driver === 'native' || !static::$extension) {
			return static::$pool[$hash] = new Native($config);
		} elseif ($driver === 'connectPool' && static::$connectPool) {
			return static::$pool[$hash] = new ConnectPool($config);
		}

		return static::$pool[$hash] = new PhpRedis($config);
	}
}