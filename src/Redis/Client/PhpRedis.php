<?php
/**
 * @author    Colin Mollenhour <colin@mollenhour.com>
 * @copyright 2011 Colin Mollenhour <colin@mollenhour.com>
 * @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 * @package   static
 */
namespace Hail\Redis\Client;

use Hail\Redis\Exception\RedisException;
use Hail\Redis\Traits\PhpRedisTrait;

/**
 * Class PhpRedis
 *
 * @package Hail\Redis\Client
 * @author  Feng Hao <flyinghail@msn.com>
 * @inheritdoc
 */
class PhpRedis extends AbstractClient
{
    use PhpRedisTrait;

	/**
	 * @throws RedisException
	 */
    public function connect()
	{
		if (!$this->redis) {
			$this->redis = new \Redis();
		}

		$socketTimeout = $this->timeout ?: 0.0;

        try
        {
            $result = $this->persistent
                ? $this->redis->pconnect($this->host, $this->port, $socketTimeout, $this->persistent)
                : $this->redis->connect($this->host, $this->port, $socketTimeout);
        }
        catch(\Exception $e)
        {
            // Some applications will capture the php error that phpredis can sometimes generate and throw it as an Exception
            $result = false;
            $errno = 1;
            $errstr = $e->getMessage();
        }

		// Use recursion for connection retries
		if (!$result) {
			$this->connectFailures++;
			if ($this->connectFailures <= $this->maxConnectRetries) {
				$this->connect();

				return;
			}
			$failures = $this->connectFailures;
			$this->connectFailures = 0;
            throw new RedisException("Connection to Redis {$this->host}:{$this->port} failed after $failures failures." . (isset($errno) && isset($errstr) ? "Last Error : ({$errno}) {$errstr}" : ''));
		}

		$this->connectFailures = 0;
		$this->connected = true;

		$this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

		// Set read timeout
		if ($this->readTimeout !== null) {
			$this->setReadTimeout($this->readTimeout);
		}

		if ($password = $this->getPassword()) {
			$this->auth($password);
		}

		if ($this->database !== 0) {
			$this->select($this->database);
		}
	}

	/**
	 * Set the read timeout for the connection. Use 0 to disable timeouts entirely (or use a very long timeout
	 * if not supported).
	 *
	 * @param int $timeout 0 (or -1) for no timeout, otherwise number of seconds
	 *
	 * @throws RedisException
	 * @return static
	 */
	public function setReadTimeout(int $timeout)
	{
		if ($timeout < -1) {
			throw new RedisException('Timeout values less than -1 are not accepted.');
		}
		$this->readTimeout = $timeout;
		if ($this->connected) {
			// supported in phpredis 2.2.3
			// a timeout value of -1 means reads will not timeout
			$timeout = $timeout === 0 ? -1 : $timeout;
			$this->redis->setOption(\Redis::OPT_READ_TIMEOUT, $timeout);
		}

		return $this;
	}


    /**
     * @param $name
     * @param $args
     *
     * @return null
     * @throws RedisException
     */
	public function __call($name, $args)
	{
		[$name, $args] = $this->normalize($name, $args);

		try {
			$response = $this->executeMethod($name, $args);
		} catch (\RedisException $e) {
			$code = 0;
			if (!$this->redis->isConnected()) {
				$this->close();
				$code = RedisException::CODE_DISCONNECTED;
			}
			throw new RedisException($e->getMessage(), $code, $e);
		}

		return $this->parseResponse($name, $response);
	}
}
