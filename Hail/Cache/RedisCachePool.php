<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Hail\Cache;

use Hail\Redis\Client\AbstractClient;
use Hail\SimpleCache\Redis;
use Hail\Facade\Serialize;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class RedisCachePool extends AbstractCachePool implements HierarchicalPoolInterface
{
    use HierarchicalCachePoolTrait;

    /**
     * @type \Hail\Redis\Client\AbstractClient
     */
    protected $cache;

	/**
	 * @type string
	 */
	protected $namespace;

	private $simple;

    public function __construct(AbstractClient $redis, $namespace = '')
    {
        $this->cache = $redis;
        $this->namespace = $namespace;
    }

    public function getSimple()
    {
        return $this->simple ?? $this->simple = new Redis([
            'client' => $this->cache,
            'namespace' => $this->namespace
        ]);
    }

    /**
	 * Add namespace prefix on the key.
	 *
	 * @param string $key
	 */
	private function prefixValue(&$key)
	{
		// |namespace|key
		$key = HierarchicalPoolInterface::HIERARCHY_SEPARATOR . $this->namespace . HierarchicalPoolInterface::HIERARCHY_SEPARATOR . $key;
	}

	/**
	 * @param array $keys
	 */
	private function prefixValues(array &$keys)
	{
		foreach ($keys as &$key) {
			$this->prefixValue($key);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getItem($key)
	{
		$this->namespace && $this->prefixValue($key);

		return parent::getItem($key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getItems(array $keys = [])
	{
		$this->namespace && $this->prefixValues($keys);

		return parent::getItems($keys);
	}

	/**
	 * {@inheritdoc}
	 */
	public function hasItem($key)
	{
		$this->namespace && $this->prefixValue($key);

		return parent::hasItem($key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function clear()
	{
		return $this->namespace ?
			parent::deleteItem(HierarchicalPoolInterface::HIERARCHY_SEPARATOR . $this->namespace) :
			parent::clear();
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteItem($key)
	{
		$this->namespace && $this->prefixValue($key);

		return parent::deleteItem($key);
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteItems(array $keys)
	{
		$this->namespace && $this->prefixValues($keys);

		return parent::deleteItems($keys);
	}

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        if (false === $result = Serialize::decode($this->cache->get($this->getHierarchyKey($key)))) {
            return [false, null, [], null];
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function clearAllObjectsFromCache()
    {
        return $this->cache->flushDb();
    }

    /**
     * {@inheritdoc}
     */
    protected function clearOneObjectFromCache($key)
    {
        $keyString = $this->getHierarchyKey($key, $path);
        if ($path) {
            $this->cache->incr($path);
        }
        $this->clearHierarchyKeyCache();

        return $this->cache->del($keyString) >= 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function storeItemInCache(CacheItemInterface $item)
    {
        $expire = $item->getExpirationTimestamp();

        $key  = $this->getHierarchyKey($item->getKey());
        $data = Serialize::encode([true, $item->get(), $item->getTags(), $expire]);
        if ($expire === null || $expire === NOW) {
            return $this->cache->set($key, $data);
        }

        return $this->cache->setEx($key, $expire - NOW, $data);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDirectValue($key)
    {
        return $this->cache->get($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $value)
    {
        $this->cache->lPush($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        return $this->cache->lRange($name, 0, -1);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        return $this->cache->del($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function removeListItem($name, $key)
    {
        return $this->cache->lRem($name, $key, 0);
    }
}
