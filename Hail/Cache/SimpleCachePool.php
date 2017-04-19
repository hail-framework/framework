<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015-2016 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Hail\Cache;

use Hail\Cache\{
    Simple\CacheInterface,
    CacheItemInterface as HailCacheItem,
    Exception\CacheException,
    Exception\CachePoolException,
    Exception\InvalidArgumentException
};
use Psr\Cache\CacheItemInterface;

/**
 * This is a PSR-6 wrapper for PSR-16 cache.
 *
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class SimpleCachePool implements CacheItemPoolInterface
{
    const SEPARATOR_TAG = '!';

    /**
     * @type HailCacheItem[] deferred
     */
    protected $deferred = [];

    /**
     * @var CacheInterface
     */
    protected $cache;


    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @inheritdoc
     */
    public function getSimple()
    {
        return $this->cache;
    }

    /**
     * Fetch an object from the cache implementation.
     *
     * If it is a cache miss, it MUST return [false, null, [], null]
     *
     * @param string $key
     *
     * @return array with [isHit, value, tags[], expirationTimestamp]
     */
    protected function fetchObjectFromCache($key)
    {
        return $this->cache->getDirectValue($key);
    }

    /**
     * Clear all objects from cache.
     *
     * @return bool false if error
     */
    protected function clearAllObjectsFromCache()
    {
        return $this->cache->clear();
    }

    /**
     * Remove one object from cache.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function clearOneObjectFromCache($key)
    {
        return $this->cache->delete($key);
    }

    /**
     * @param HailCacheItem $item
     *
     * @return bool true if saved
     */
    protected function storeItemInCache(HailCacheItem $item)
    {
        return $this->cache->setDirectValue(
            $item->getKey(),
            $item->get(),
            $item->getTags(),
            $item->getExpirationTimestamp()
        );
    }

    /**
     * Get an array with all the values in the list named $name.
     *
     * @param string $name
     *
     * @return array
     */
    protected function getList($name)
    {
        if (null === ($list = $this->cache->get($name)) || !is_array($list)) {
            return [];
        }

        return $list;
    }

    /**
     * Remove the list.
     *
     * @param string $name
     *
     * @return bool
     */
    protected function removeList($name)
    {
        return $this->cache->set($name, [], 0);
    }

    /**
     * Add a item key on a list named $name.
     *
     * @param string $name
     * @param string $key
     */
    protected function appendListItem($name, $key)
    {
        $list = $this->getList($name);
        $list[] = $key;
        $this->cache->set($name, $list, 0);
    }

    /**
     * Remove an item from the list.
     *
     * @param string $name
     * @param string $key
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

    /**
     * Make sure to commit before we destruct.
     */
    public function __destruct()
    {
        $this->commit();
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $this->validateKey($key);
        if (isset($this->deferred[$key])) {
            /** @type CacheItem $item */
            $item = clone $this->deferred[$key];
            $item->moveTagsToPrevious();

            return $item;
        }

        try {
            $value = $this->fetchObjectFromCache($key);

            return new CacheItem($key,
                $value[0] ?? false,
                $value[1] ?? null,
                $value[2] ?? [],
                $value[3] ?? null
            );
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        $items = [];
        foreach ($keys as $key) {
            $items[$key] = $this->getItem($key);
        }

        return $items;
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        try {
            return $this->getItem($key)->isHit();
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        // Clear the deferred items
        $this->deferred = [];

        try {
            return $this->clearAllObjectsFromCache();
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        try {
            return $this->deleteItems([$key]);
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $deleted = true;
        foreach ($keys as $key) {
            $this->validateKey($key);

            // Delete form deferred
            unset($this->deferred[$key]);

            // We have to commit here to be able to remove deferred hierarchy items
            $this->commit();
            $this->preRemoveItem($key);

            if (!$this->clearOneObjectFromCache($key)) {
                $deleted = false;
            }
        }

        return $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function save(CacheItemInterface $item)
    {
        if (!$item instanceof HailCacheItem) {
            $e = new InvalidArgumentException('Cache items are not transferable between pools. Item MUST implement PhpCacheItem.');
            $this->handleException($e, __FUNCTION__);
        }

        $this->removeTagEntries($item);
        $this->saveTags($item);

        $timestamp = $item->getExpirationTimestamp();
        if (null !== $timestamp) {
            $timeToLive = $timestamp - NOW;

            if ($timeToLive < 0) {
                return $this->deleteItem($item->getKey());
            }
        }

        try {
            return $this->storeItemInCache($item);
        } catch (\Exception $e) {
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function commit()
    {
        $saved = true;
        foreach ($this->deferred as $item) {
            if (!$this->save($item)) {
                $saved = false;
            }
        }
        $this->deferred = [];

        return $saved;
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (!is_string($key)) {
            $e = new InvalidArgumentException(sprintf(
                'Cache key must be string, "%s" given', gettype($key)
            ));
            $this->handleException($e, __FUNCTION__);
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            $e = new InvalidArgumentException(sprintf(
                'Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:',
                $key
            ));
            $this->handleException($e, __FUNCTION__);
        }
    }

    /**
     * Log exception and rethrow it.
     *
     * @param \Exception $e
     * @param string     $function
     *
     * @throws CachePoolException
     */
    private function handleException(\Exception $e, $function)
    {
        if (!$e instanceof CacheException) {
            $e = new CachePoolException('Exception thrown when executing "' . $function . '". ', 0, $e);
        }

        throw $e;
    }

    /**
     * @inheritdoc
     */
    public function invalidateTags(array $tags)
    {
        $itemIds = [];
        foreach ($tags as $tag) {
            $itemIds[] = $this->getList($this->getTagKey($tag));
        }
        $itemIds = array_merge(...$itemIds);

        // Remove all items with the tag
        $success = $this->deleteItems($itemIds);

        if ($success) {
            // Remove the tag list
            foreach ($tags as $tag) {
                $this->removeList($this->getTagKey($tag));
                $l = $this->getList($this->getTagKey($tag));
            }
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function invalidateTag($tag)
    {
        return $this->invalidateTags([$tag]);
    }

    /**
     * @param HailCacheItem $item
     */
    protected function saveTags(HailCacheItem $item)
    {
        $tags = $item->getTags();
        foreach ($tags as $tag) {
            $this->appendListItem($this->getTagKey($tag), $item->getKey());
        }
    }

    /**
     * Removes the key form all tag lists. When an item with tags is removed
     * we MUST remove the tags. If we fail to remove the tags a new item with
     * the same key will automatically get the previous tags.
     *
     * @param string $key
     *
     * @return $this
     */
    protected function preRemoveItem($key)
    {
        $item = $this->getItem($key);
        $this->removeTagEntries($item);

        return $this;
    }

    /**
     * @param HailCacheItem $item
     */
    private function removeTagEntries(HailCacheItem $item)
    {
        $tags = $item->getPreviousTags();
        foreach ($tags as $tag) {
            $this->removeListItem($this->getTagKey($tag), $item->getKey());
        }
    }

    /**
     * @param string $tag
     *
     * @return string
     */
    protected function getTagKey($tag)
    {
        return 'tag' . self::SEPARATOR_TAG . $tag;
    }
}

