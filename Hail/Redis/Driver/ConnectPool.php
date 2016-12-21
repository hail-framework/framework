<?php
namespace Hail\Redis\Driver;

use Hail\Redis\Exception\RedisException;

/**
 * Class ConnectPool
 *
 * @package Hail\Redis\Driver
 * @inheritdoc
 */
class ConnectPool extends PhpRedis
{
	/**
	 * @throws RedisException
	 */
	protected function connect()
	{
		$this->redis = new \redisProxy();
		parent::connect();
	}

	/**
	 * @return bool
	 */
	public function close()
	{
		$this->connected = false;
		return true;
	}

	public function __call($name, $args)
	{
		try {
			$response = parent::__call($name, $args);
		} finally {
			if ($this->redisMulti === null) {
				$this->redis->release();
			}
		}

		return $response;
	}
}
