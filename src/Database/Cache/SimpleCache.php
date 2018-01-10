<?php

namespace Hail\Database\Cache;

use Hail\Database\Database;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache database query with PSR16
 *
 * @package Hail\Database
 * @author  Hao Feng <flyinghail@msn.com>
 *
 * @method array|null select(array|string $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method array|string|null get(array|string $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
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
     * @param string $name
     * @param array  $arguments
     *
     * @return array|bool|mixed
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function __call($name, $arguments)
    {
        $key = $this->key($name, $arguments);

        $result = $this->cache->get($key);

        if ($result === null) {
            $result = $this->call($name, $arguments);
            $this->cache->set($key, $result, $this->lifetime);
        }

        $this->reset();

        return $result;
    }

    /**
     * @param      $struct
     * @param int  $fetch
     * @param null $fetchArgs
     *
     * @return \Generator
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function selectRow($struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null): \Generator
    {
        $args = [$struct, $fetch];
        if ($fetchArgs !== null) {
            $args[] = $fetchArgs;
        }

        $key = $this->key('selectRow', $args);

        $count = $this->cache->get($key . '_count');
        if ($count === null) {
            $rows = $this->db->selectRow($struct, $fetch, $fetchArgs);
            if (!$rows->valid()) {
                return;
            }

            $index = 0;
            foreach ($rows as $row) {
                yield $row;

                $this->cache->set($key . '_' . $index++, $row, $this->lifetime ? $this->lifetime + 5 : 0);
            }

            $this->cache->set($key . '_count', $index, $this->lifetime);
        } else {
            for ($i = 0; $i < $count; ++$i) {
                yield $this->cache->get($key . '_' . $i);
            }
        }
    }

    /**
     * @param string $key
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function doDelete($key): bool
    {
        return $this->cache->delete($key);
    }
}