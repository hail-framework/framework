<?php

namespace Hail\Database\Cache;

use Hail\Database\Database;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache database query with PSR16
 *
 * @package Hail\Database
 * @author  Hao Feng <flyinghail@msn.com>
 */
class SimpleCache implements CachedDBInterface
{
    use CacheTrait;

    /**
     * @var CacheInterface
     */
    protected $cache;

    public function __construct(Database $db, CacheInterface $cache)
    {
        $this->db = $db;
        $this->cache = $cache;
    }

    /**
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function doGet()
    {
        return $this->cache->get($this->name);
    }

    /**
     * @param mixed $result
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function doSave($result)
    {
        $this->cache->set($this->name, $result, $this->lifetime);
    }

    /**
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function doDelete(): bool
    {
        return $this->cache->delete($this->name);
    }
}