<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Hail\Cache\Driver;

use Hail\Cache\Driver;
use Hail\Utils\Serialize;

/**
 * Redis cache provider.
 *
 * @link   www.doctrine-project.org
 * @since  2.2
 * @author Osman Ungur <osmanungur@gmail.com>
 * @author FlyingHail <flyinghail@msn.com>
 */
class Redis extends Driver
{
	/**
	 * @var Redis|null
	 */
	private $redis;

	// use php-cp extension
	protected $extConnectPool = false;

	protected function release()
	{
		if ($this->extConnectPool) {
			$this->redis->release();
		}
	}

	public function __construct($params)
	{
		$params = array_merge(
			\Config::get('redis'), $params
		);

		if (isset($params['servers'])) {
			$paramServers = (array) $params['servers'];
			unset($params['servers']);

			$servers = [];
			foreach ($paramServers as $server) {
				if (isset($server['socket'])) {
					$servers[] = ['socket' => $server['socket']];
				} else {
					$host = $server['server'] ?? $server[0] ?? '127.0.0.1';
					$port = $server['port'] ?? $server[1] ?? 6379;
					$servers[] = ['host' => $host, 'port' => $port];
				}
			}
		} else {
			$servers = [
				['host' => '127.0.0.1', 'port' => 6379],
			];
		}

		if ($params['extConnectPool']) {
			$this->extConnectPool = count($servers) === 1 && class_exists('\redisProxy');
		}

		// this will have to be revisited to support multiple servers, using
		// the RedisArray object. That object acts as a proxy object, meaning
		// most of the class will be the same even after the changes.
		if (count($servers) === 1) {
			$server = $servers[0];
			$redis = $this->extConnectPool ? new \redisProxy() : new \Redis();
			if (!empty($server['socket'])) {
				$redis->connect($server['socket']);
			} else {
				$redis->connect($server['host'], $server['port']);
			}

			// auth - just password
			if (isset($params['password'])) {
				$redis->auth($params['password']);
			}
			$this->redis = $redis;
		} else {
			$options = [];
			foreach (
				[
					'previous',
					'function',
					'distributor',
					'index',
					'autorehash',
					'pconnect',
					'retry_interval',
					'lazy_connect',
					'connect_timeout',
				] as $name
			) {
				if (isset($params[$name])) {
					$options[$name] = $params[$name];
				}
			}

			$array = [];
			foreach ($servers as $server) {
				$str = $server['host'];
				if (isset($server['port'])) {
					$str .= ':' . $server['port'];
				}
				$array[] = $str;
			}
			$redis = new \RedisArray($array, $options);
		}

		// select database
		if (isset($params['database'])) {
			$redis->select($params['database']);
		}

		$redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
		$this->redis = $redis;

		parent::__construct($params);
	}

	/**
	 * Gets the redis instance used by the cache.
	 *
	 * @return Redis|null
	 */
	public function getRedis()
	{
		return $this->redis;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		$return = Serialize::decode(
			$this->redis->get($id)
		);
		$this->release();

		return $return;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
	{
		$success = true;

		$lifetime = (int) $lifetime;
		if ($lifetime > 0) {
			// Keys have lifetime, use SETEX for each of them
			foreach ($keysAndValues as $key => $value) {
				if (!$this->redis->setex($key, $lifetime, Serialize::encode($value))) {
					$success = false;
				}
				$this->release();
			}
		} else {
			// No lifetime, use MSET
			$success = $this->redis->mset(
				Serialize::encodeArray($keysAndValues)
			);
			$this->release();
		}

		return $success;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetchMultiple(array $keys)
	{
		$fetchedItems = array_combine($keys, $this->redis->mget($keys));

		// Redis mget returns false for keys that do not exist. So we need to filter those out unless it's the real data.
		$foundItems = [];

		foreach ($fetchedItems as $key => $value) {
			$value = Serialize::decode($value);
			if (false !== $value || $this->redis->exists($key)) {
				$foundItems[$key] = $value;
			}
			$this->release();
		}

		return $foundItems;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		$return = $this->redis->exists($id);
		$this->release();

		return $return;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifetime = 0)
	{
		$data = Serialize::encode($data);
		if ($lifetime > 0) {
			$return = $this->redis->setex($id, $lifetime, $data);
		} else {
			$return = $this->redis->set($id, $data);
		}
		$this->release();

		return $return;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		$return = $this->redis->delete($id);
		$this->release();

		return $return >= 0;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		$return = $this->redis->flushDB();
		$this->release();

		return $return;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		$info = $this->redis->info();
		$this->release();

		return [
			Driver::STATS_HITS => $info['keyspace_hits'],
			Driver::STATS_MISSES => $info['keyspace_misses'],
			Driver::STATS_UPTIME => $info['uptime_in_seconds'],
			Driver::STATS_MEMORY_USAGE => $info['used_memory'],
			Driver::STATS_MEMORY_AVAILABLE => false,
		];
	}
}
