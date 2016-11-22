<?php

namespace Hail\Cache\Driver;

use Hail\Cache\Driver;
use Hail\Utils\Arrays;
use Hail\Utils\Serialize;

/**
 * Predis cache provider.
 *
 * @author othillo <othillo@othillo.nl>
 * @author Hao Feng <flyinghail@msn.com>
 */
class Predis extends Driver
{
	/**
	 * @var ClientInterface
	 */
	private $client;

	/**
	 * Predis constructor.
	 * @param array $params [client => \Predis\ClientInterface]
	 */
	public function __construct($params)
	{
		$params = array_merge(
			\Config::get('redis'), $params
		);

		if (isset($params['servers'])) {
			$paramServers = (array)$params['servers'];
			unset($params['servers']);

			$servers = [];
			foreach ($paramServers as $server) {
				if (isset($server['socket'])) {
					$alias = $server['alias'] ?? '';
					$servers[] = ['scheme' => 'unix', 'path' => $server['socket'], 'alias' => $alias];
				} else {
					$host = $server['server'] ?? $server[0] ?? '127.0.0.1';
					$port = $server['port'] ?? $server[1] ?? 6379;
					$alias = $server['alias'] ?? $server[2] ?? '';
					$servers[] = ['scheme' => 'tcp', 'host' => $host, 'port' => $port, 'alias' => $alias];
				}
			}
		} else {
			$servers = ['scheme' => 'tcp', 'host' => '127.0.0.1', 'port' => 6379];
		}

		$options = array();
		foreach (
			[
				'profile',
				'prefix',
				'exceptions',
				'connections',
				'cluster',
				'replication',
				'aggregate',

			] as $name
		) {
			if (isset($params[$name])) {
				$options[$name] = $params[$name];
			}
		}

		$this->client = new \Predis\Client($servers, $options);

		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		$result = $this->client->get($id);
		if (null === $result) {
			return false;
		}

		return Serialize::decode($result);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSaveMultiple(array $keysAndValues, $lifetime = 0)
	{
		$success = true;

		if (!$lifetime) {
			// No lifetime, use MSET
			$response = $this->client->mset(
				Serialize::encodeArray($keysAndValues)
			);

			$success = ((string)$response === 'OK');
		} else {
			// Keys have lifetime, use SETEX for each of them
			foreach ($keysAndValues as $key => $value) {
				$response = $this->client->setex($key, $lifetime,
					Serialize::encode($value)
				);

				if ((string)$response !== 'OK') {
					$success = false;
				}
			}
		}

		return $success;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetchMultiple(array $keys)
	{
		$fetchedItems = call_user_func_array(array($this->client, 'mget'), $keys);

		return Serialize::decodeArray(
			Arrays::filter(
				array_combine($keys, $fetchedItems)
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		return $this->client->exists($id);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifetime = 0)
	{
		$data = Serialize::encode($data);
		if ($lifetime > 0) {
			$response = $this->client->setex($id, $lifetime, $data);
		} else {
			$response = $this->client->set($id, $data);
		}

		return $response === true || $response == 'OK';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		return $this->client->del($id) >= 0;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		$response = $this->client->flushdb();

		return $response === true || $response == 'OK';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		$info = $this->client->info();

		return array(
			Driver::STATS_HITS => $info['Stats']['keyspace_hits'],
			Driver::STATS_MISSES => $info['Stats']['keyspace_misses'],
			Driver::STATS_UPTIME => $info['Server']['uptime_in_seconds'],
			Driver::STATS_MEMORY_USAGE => $info['Memory']['used_memory'],
			Driver::STATS_MEMORY_AVAILABLE => false
		);
	}
}
