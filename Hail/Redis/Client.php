<?php
namespace Hail\Redis;

use Hail\Facades\{
	Config, Serialize
};
use Hail\Redis\Client\{
	AbstractClient, Native, PhpRedis, ConnectPool
};
use Hail\Redis\Exception\RedisException;

class Client
{
	/**
	 * @var AbstractClient[]
	 */
	protected static $pool = [];
	protected static $config = [];
	protected static $extension = true;
	protected static $connectPool = false;

	/**
	 * @param array $config
	 *
	 * @return AbstractClient
	 * @throws RedisException
	 */
	public static function get(array $config): AbstractClient
	{
		if (static::$pool === []) {
			static::$config = Config::get('redis');
			static::$extension = extension_loaded('redis');
			static::$connectPool = class_exists('\redisProxy', false);
		}

		$config += static::$config;
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