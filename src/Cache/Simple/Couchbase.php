<?php
namespace Hail\Cache\Simple;

use CouchbaseCluster;
use CouchbaseBucket;

/**
 * Couchbase cache driver.
 *
 * @author Feng Hao <flyinghail@msn.com>
 */
class Couchbase extends AbstractAdapter
{
    /**
     * @var CouchbaseCluster
     */
    private $couchbase;

	/**
	 * @var CouchbaseBucket
	 */
    private $bucket;

	public function __construct($config)
	{
		$this->couchbase = new CouchbaseCluster(
			$config['dsn'],
			$config['username'] ?? null,
			$config['password'] ?? null
		);

		$this->bucket = $this->couchbase->openBucket($config['bucket'] ?? 'default');

		parent::__construct($config);
	}

    /**
     * {@inheritdoc}
     */
    protected function doGet(string $key)
    {
        return $this->bucket->get($key);
    }

	/**
	 * {@inheritdoc}
	 */
	protected function doGetMultiple(array $keys)
	{
		return $this->bucket->get($keys) ?: [];
	}

    /**
     * {@inheritdoc}
     */
    protected function doHas(string $key)
    {
        return (null !== $this->bucket->get($key));
    }

    /**
     * {@inheritdoc}
     */
    protected function doSet(string $key, $value, int $ttl = 0)
    {
        return $this->bucket->upsert($key, $value, [
        	'expiry' => $ttl
        ]);
    }

	/**
	 * {@inheritdoc}
	 */
	protected function doSetMultiple(array $values, int $ttl = 0)
	{
		return $this->bucket->upsert($values, null, [
			'expiry' => $ttl
		]);
	}

    /**
     * {@inheritdoc}
     */
    protected function doDelete(string $key)
    {
        return $this->bucket->remove($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function doClear()
    {
	    $this->bucket->manager()->flush();
        return true;
    }
}
