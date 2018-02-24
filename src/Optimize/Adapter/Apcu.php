<?php

namespace Hail\Optimize\Adapter;


use Hail\Optimize\AdapterInterface;

class Apcu implements AdapterInterface
{
    public static function init(): bool
    {
        return \extension_loaded('apcu');
    }

    public static function get(string $key)
    {
        return \apcu_fetch($key);
    }

    public static function set(string $key, $value, int $ttl = 0)
    {
        return \apcu_store($key, $value, $ttl);
    }

    public static function setMultiple(array $values, int $ttl = 0)
    {
        $result = \apcu_store($values, null, $ttl);

        return !($result === false || \count($result) > 0);
    }
}