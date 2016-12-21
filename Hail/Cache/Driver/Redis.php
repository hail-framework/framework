<?php
namespace Hail\Cache\Driver;

use Hail\Cache\Driver;
use Hail\Facades\{
	Config,
	Serialize
};
use Hail\Redis\{
	Exception\RedisException,
	Factory as RedisFactory
};

/**
 * Redis cache provider.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class Redis extends Driver
{
	/**
	 * @var \Hail\Redis\Driver|null
	 */
	private $redis;

	/**
	 * Redis constructor.
	 *
	 * @param array $params
	 * @throws RedisException
	 */
	public function __construct(array $params)
	{
		$params +=  Config::get('redis');

		$this->redis = RedisFactory::client($params);

		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		return Serialize::decode(
			$this->redis->get($id)
		);
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
				if (!$this->redis->setEx($key, $lifetime, Serialize::encode($value))) {
					$success = false;
				}
			}
		} else {
			// No lifetime, use MSET
			$success = $this->redis->mSet(
				Serialize::encodeArray($keysAndValues)
			);
		}

		return $success;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetchMultiple(array $keys)
	{
		$fetchedItems = array_combine($keys, $this->redis->mGet($keys));

		// Redis mget returns false for keys that do not exist. So we need to filter those out unless it's the real data.
		$foundItems = [];
		foreach ($fetchedItems as $key => $value) {
			if (false !== $value || $this->redis->exists($key)) {
				$foundItems[$key] = Serialize::decode($value);
			}
		}

		return $foundItems;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return $this->redis->exists($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifetime = 0)
	{
		$data = Serialize::encode($data);
		if ($lifetime > 0) {
			return $this->redis->setEx($id, $lifetime, $data);
		} else {
			return $this->redis->set($id, $data);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		$return = $this->redis->del($id);
		return $return >= 0;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		return $this->redis->flushDb();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		$info = $this->redis->info();

		return [
			Driver::STATS_HITS => $info['keyspace_hits'],
			Driver::STATS_MISSES => $info['keyspace_misses'],
			Driver::STATS_UPTIME => $info['uptime_in_seconds'],
			Driver::STATS_MEMORY_USAGE => $info['used_memory'],
			Driver::STATS_MEMORY_AVAILABLE => false,
		];
	}
}
