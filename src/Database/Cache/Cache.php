<?php

namespace Hail\Database\Cache;

use Hail\Database\Database;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Cache database query with PSR6
 *
 * @package Hail\Database
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Cache extends AbstractCache
{
     /**
     * @var CacheItemPoolInterface
     */
    protected $cache;

    public function __construct(Database $db, CacheItemPoolInterface $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * @return \Psr\Cache\CacheItemInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doGet()
    {
        return $this->cache->getItem($this->name);
    }

    /**
     * @param \Psr\Cache\CacheItemInterface $cache
     *
     * @return mixed
     */
    protected function getResult($cache)
    {
        if (!$cache->isHit()) {
            return null;
        }

        return $cache->get();
    }

    /**
     * @param mixed                              $result
     * @param \Psr\Cache\CacheItemInterface|null $cache
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doSave($result, $cache = null)
    {
        if ($cache === null) {
            $cache = $this->doGet();
        }

        $this->cache->save(
            $cache->expiresAfter($this->lifetime)->set($result)
        );
    }

    /**
     * @return bool
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doDelete(): bool
    {
        return $this->cache->deleteItem($this->name);
    }
}