<?php

namespace Hail\Cache;

use Hail\SimpleCache\CacheInterface;

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

    public function getSimple()
    {
        return $this->cache;
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchObjectFromCache($key)
    {
        return $this->cache->getDirectValue($key);
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
    protected function storeItemInCache(CacheItemInterface $item)
    {
        return $this->cache->setDirectValue(
            $item->getKey(),
            $item->get(),
            $item->getTags(),
            $item->getExpirationTimestamp()
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getList($name)
    {
        if (null === ($list = $this->cache->get($name)) || !is_array($list)) {
            return [];
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    protected function removeList($name)
    {
        return $this->cache->set($name, [], 0);
    }

    /**
     * {@inheritdoc}
     */
    protected function appendListItem($name, $key)
    {
        $list = $this->getList($name);
        $list[] = $key;
        $this->cache->set($name, $list, 0);
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
        $this->cache->set($name, $list, 0);
    }
}

