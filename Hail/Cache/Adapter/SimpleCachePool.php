<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Hail\Cache\Adapter;

use Hail\Facades\Cache;
use Hail\Cache\CacheItemInterface as HailCacheItem;
use Hail\Cache\TaggableItemInterface;
use Hail\Cache\TaggablePoolInterface;
use Hail\Cache\TaggablePoolTrait;
use Psr\Cache\CacheItemInterface;

/**
 * This is a bridge between PSR-6 and PSR-16 cache.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class SimpleCachePool extends AbstractCachePool implements TaggablePoolInterface
{
	/**
	 * @var \Hail\SimpleCache\CacheInterface
	 */
	private $cache;

	use TaggablePoolTrait;

	public function __construct()
	{
		$this->cache = Cache::getInstance();
	}

	/**
	 * {@inheritdoc}
	 */
	public function save(CacheItemInterface $item)
	{
		if ($item instanceof TaggableItemInterface) {
			$this->saveTags($item);
		}

		return parent::save($item);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function fetchObjectFromCache($key)
	{
		if (null === $data = $this->cache->get($key)) {
			return [false, null, [], null];
		}

		return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function clearAllObjectsFromCache()
	{
		return $this->cache->clear();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function clearOneObjectFromCache($key)
	{
		$this->preRemoveItem($key);

		return $this->cache->delete($key);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function storeItemInCache(HailCacheItem $item, $ttl)
	{
		if ($ttl === null) {
			$ttl = 0;
		}

		$tags = [];
		if ($item instanceof TaggableItemInterface) {
			$tags = $item->getTags();
		}
		$data = [true, $item->get(), $tags, $item->getExpirationTimestamp()];

		return $this->cache->set($item->getKey(), $data, $ttl);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function getList($name)
	{
		if (false === $list = $this->cache->get($name)) {
			return [];
		}

		return $list;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function removeList($name)
	{
		return $this->cache->delete($name);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function appendListItem($name, $key)
	{
		$list = $this->getList($name);
		$list[] = $key;
		$this->cache->set($name, $list);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function removeListItem($name, $key)
	{
		$list = $this->getList($name);
		foreach ($list as $i => $item) {
			if ($item === $key) {
				unset($list[$i]);
			}
		}
		$this->cache->set($name, $list);
	}
}

