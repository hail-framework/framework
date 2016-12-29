<?php
/**
 * Credis, a Redis interface for the modest
 *
 * @author    Justin Poliey <jdp34@njit.edu>
 * @copyright 2009 Justin Poliey <jdp34@njit.edu>
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   Credis
 */
namespace Hail\Redis;

use Hail\Redis\Client\{
	AbstractClient, Native, PhpRedis, ConnectPool
};
use Hail\Redis\Exception\RedisException;

class Client
{
	/**
	 * @param array $config
	 *
	 * @return AbstractClient
	 * @throws RedisException
	 */
	public static function get(array $config): AbstractClient
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

