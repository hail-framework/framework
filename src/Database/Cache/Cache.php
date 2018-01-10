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
 * @method array|null select(array|string $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
 * @method array|string|null get(array|string $struct, int $fetch = \PDO::FETCH_ASSOC, mixed $fetchArgs = null)
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
     * @param string $name
     * @param array  $arguments
     *
     * @return array|bool|mixed
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function __call($name, $arguments)
    {
        $key = $this->key($name, $arguments);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            $result = $item->get();
        } else {
            $result = $this->call($name, $arguments);

            $this->cache->save(
                $item->expiresAfter($this->lifetime)->set($result)
            );
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

        $item = $this->cache->getItem($key . '_count');

        if ($item->isHit()) {
            for ($i = 0, $n = $item->get(); $i < $n; ++$i) {
                yield $this->cache->getItem($key . '_' . $i)->get();
            }
        } else {
            $rows = $this->db->selectRow($struct, $fetch, $fetchArgs);
            if (!$rows->valid()) {
                return;
            }

            $index = 0;
            foreach ($rows as $row) {
                yield $row;

                $current = $this->cache->getItem($key . '_' . $index++);
                $this->cache->save(
                    $current->expiresAfter($this->lifetime ? $this->lifetime + 5: 0)->set($row)
                );
            }

            $this->cache->save(
                $item->expiresAfter($this->lifetime)->set($index)
            );
        }
    }

    /**
     * @param string $key
     * @return bool
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doDelete($key): bool
    {
        return $this->cache->deleteItem($key);
    }
}