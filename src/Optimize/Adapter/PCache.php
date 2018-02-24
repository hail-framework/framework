<?php

namespace Hail\Optimize\Adapter;


use Hail\Optimize\AdapterInterface;

class PCache implements AdapterInterface
{
    public static function init(): bool
    {
        return \extension_loaded('pcache');
    }

    public static function get(string $key)
    {
        return \pcache_get($key);
    }

    public static function set(string $key, $value, int $ttl = 0)
    {
        return \pcache_set($key, $value, $ttl);
    }

    public static function setMultiple(array $values, int $ttl = 0)
    {
        $return = true;
        foreach ($values as $k => $v) {
            if (!\pcache_set($k, $v, $ttl)) {
                $return = false;
            }
        }

        return $return;
    }
}