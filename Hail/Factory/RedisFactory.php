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

class RedisFactory
{
	/**
	 * @var AbstractClient[]
	 */
	protected static $pool = [];
	protected static $extension;
	protected static $connectPool;

	/**
	 * @param array $config
	 *
	 * @return AbstractClient
	 * @throws RedisException
	 */
	public static function get(array $config)
	{
		return static::client($config);
	}

	public static function client(array $config = []): AbstractClient
	{
		$config += Config::get('redis');
		$hash = sha1(Serialize::encode($config));

		if (isset(static::$pool[$hash])) {
			return static::$pool[$hash];
		}

		$driver = $config['driver'] ?? '';

		if ($driver === 'native' || !(
				static::$extension ?? (static::$extension = extension_loaded('redis'))
			)
		) {
			return static::$pool[$hash] = new Native($config);
		} elseif ($driver === 'connectPool' && (
				static::$connectPool ?? (static::$connectPool = class_exists('\redisProxy', false))
			)
		) {
			return static::$pool[$hash] = new ConnectPool($config);
		}

		return static::$pool[$hash] = new PhpRedis($config);
	}
}