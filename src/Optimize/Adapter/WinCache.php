<?php

namespace Hail\Optimize\Adapter;


use Hail\Optimize\AdapterInterface;

class WinCache implements AdapterInterface
{
    public static function init(): bool
    {
        return \extension_loaded('wincache');
    }

    public static function get(string $key)
    {
        $value = \wincache_ucache_get($key, $success);
        if ($success === false) {
            return false;
        }

        return $value;
    }

    public static function set(string $key, $value, int $ttl = 0)
    {
        return \wincache_ucache_set($key, $value, $ttl);
    }

    public static function setMultiple(array $values, int $ttl = 0)
    {
        $result = \wincache_ucache_set($values, null, $ttl);

        return !($result === false || \count($result));
    }
}