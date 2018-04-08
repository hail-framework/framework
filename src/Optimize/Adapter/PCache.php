<?php

namespace Hail\Optimize\Adapter;

\defined('PCACHE_EXTENSION') || \define('PCACHE_EXTENSION', \extension_loaded('pcache'));

use Hail\Optimize\AdapterInterface;

class PCache implements AdapterInterface
{
    private static $instance;

    public static function getInstance(array $config): ?AdapterInterface
    {
        if (!PCACHE_EXTENSION) {
            return null;
        }

        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function get(string $key)
    {
        return \pcache_get($key);
    }

    public function set(string $key, $value, int $ttl = 0)
    {
        return \pcache_set($key, $value, $ttl);
    }

    public function setMultiple(array $values, int $ttl = 0)
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