<?php
namespace Hail\Redis;

use Hail\Redis\Driver\{
	Native,
	PhpRedis,
	ConnectPool
};
use Hail\Redis\Exception\RedisException;

class Factory
{
	/**
	 * @param array $config
	 *
	 * @return Driver
	 * @throws RedisException
	 */
	public static function client(array $config): Driver
	{
		$driver = $config['driver'] ?? '';

		if ($driver === 'native' || !extension_loaded('redis')) {
			return new Native($config);
		} elseif ($driver === 'connectPool' && class_exists('\redisProxy', false)) {
			return new ConnectPool($config);
		}

		return new PhpRedis($config);
	}
}
