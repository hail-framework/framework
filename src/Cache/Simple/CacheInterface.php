<?php

namespace Hail\Cache\Simple;

use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Hail\Cache\CacheItemPoolInterface;

/**
 * Interface CacheInterface
 *
 * @package Hail\Cache\Simple
 * @author  Feng Hao <flyinghail@msn.com>
 */
interface CacheInterface extends PsrCacheInterface, \ArrayAccess
{
    /**
     * Deletes all cache entries in the current cache namespace.
     *
     * @return bool TRUE if the cache entries were successfully deleted, FALSE otherwise.
     */
    public function deleteAll();

    /**
     * Sets the namespace to prefix all cache ids with.
     *
     * @param string $namespace
     *
     * @return void
     */
    public function setNamespace(string $namespace);

    /**
     * Retrieves the namespace that prefixes all cache ids.
     *
     * @return string
     */
    public function getNamespace();

    /**
     * Convert PSR-6 cache to PSR-16 simple cache
     *
     * @return CacheItemPoolInterface
     */
    public function getPool();

    /**
     * Set value for struct
     *
     * @param string $key
     * @param mixed  $value
     * @param array  $tags
     * @param int    $expire
     *
     * @return bool
     */
    public function setRawValue(string $key, $value, array $tags = [], int $expire = null);

    /**
     * @param string $key
     *
     * @return array
     */
    public function getRawValue(string $key);
}