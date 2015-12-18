<?php

namespace Hail\Cache\Driver;

use Hail\Cache\Driver;

use Predis\ClientInterface;

/**
 * Predis cache provider.
 *
 * @author othillo <othillo@othillo.nl>
 */
class Predis extends Driver
{
    /**
     * @var ClientInterface
     */
    private $client;

	/**
	 * Predis constructor.
	 * @param array $params [client => ClientInterface]
	 */
	public function __construct($params)
	{
		$this->client = $params['client'] ?? null;
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

        return unserialize($result);
    }

    /**
     * {@inheritdoc}
     */
    protected function doFetchMultiple(array $keys)
    {
        $fetchedItems = call_user_func_array(array($this->client, 'mget'), $keys);

        return array_map('unserialize', array_filter(array_combine($keys, $fetchedItems)));
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
        $data = serialize($data);
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
	        Driver::STATS_HITS              => $info['Stats']['keyspace_hits'],
	        Driver::STATS_MISSES            => $info['Stats']['keyspace_misses'],
	        Driver::STATS_UPTIME            => $info['Server']['uptime_in_seconds'],
	        Driver::STATS_MEMORY_USAGE      => $info['Memory']['used_memory'],
	        Driver::STATS_MEMORY_AVAILABLE  => false
        );
    }
}
