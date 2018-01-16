<?php

namespace Hail\Database\Cache;

use Hail\Database\Database;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Cache database query with PSR6
 *
 * @package Hail\Database
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method array|null select(array | string $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method array|string|null get(array | string $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 */
class Cache implements CachedDBInterface
{
    use CacheTrait;

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