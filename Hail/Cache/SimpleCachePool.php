<?php
namespace Hail\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * This is a bridge between PSR-6 and PSR-16 cache.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class SimpleCachePool extends AbstractCachePool
{
	/**
	 * @var CacheInterface
	 */
	private $cache;


	public function __construct(CacheInterface $cache)
	{
		$this->cache = $cache;
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
		return $this->cache->delete($key);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function storeItemInCache(CacheItemInterface $item, $ttl)
	{
		if ($ttl === null) {
			$ttl = 0;
		}

		$data = [true, $item->get(), $item->getTags(), $item->getExpirationTimestamp()];

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

