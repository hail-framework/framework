<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Hail\Cache;

use Hail\Cache\Simple\CacheInterface;

/**
 * Prefix all the stored items with a namespace. Also make sure you can clear all items
 * in that namespace.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class NamespacedCachePool extends HierarchicalCachePool implements CacheItemPoolInterface
{
    /**
     * @type string
     */
    private $namespace;

    /**
     * @param CacheInterface $cache
     * @param string         $namespace
     */
    public function __construct(CacheInterface $cache, $namespace)
    {
        $this->namespace = $namespace;

        parent::__construct($cache);
    }

    /**
     * Add namespace prefix on the key.
     *
     * @param string $key
     */
    private function prefixValue(&$key)
    {
        // |namespace|key
        $key = self::HIERARCHY_SEPARATOR . $this->namespace . self::HIERARCHY_SEPARATOR . $key;
    }

    /**
     * @param array $keys
     */
    private function prefixValues(array &$keys)
    {
        foreach ($keys as &$key) {
            $this->prefixValue($key);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getItem($key)
    {
        $this->prefixValue($key);

        return parent::getItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function getItems(array $keys = [])
    {
        $this->prefixValues($keys);

        return parent::getItems($keys);
    }

    /**
     * {@inheritdoc}
     */
    public function hasItem($key)
    {
        $this->prefixValue($key);

        return parent::hasItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return parent::deleteItem(self::HIERARCHY_SEPARATOR . $this->namespace);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItem($key)
    {
        $this->prefixValue($key);

        return parent::deleteItem($key);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteItems(array $keys)
    {
        $this->prefixValues($keys);

        return parent::deleteItems($keys);
    }
}