<?php

namespace Hail\Optimize\Adapter;

\defined('WINCACHE_EXTENSION') || \define('WINCACHE_EXTENSION', \extension_loaded('wincache'));

use Hail\Optimize\AdapterInterface;

class WinCache implements AdapterInterface
{
    private static $instance;

    public static function getInstance(array $config): ?AdapterInterface
    {
        if (!WINCACHE_EXTENSION) {
            return null;
        }

        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function get(string $key)
    {
        $value = \wincache_ucache_get($key, $success);
        if ($success === false) {
            return false;
        }

        return $value;
    }

    public function set(string $key, $value, int $ttl = 0)
    {
        return \wincache_ucache_set($key, $value, $ttl);
    }

    public function setMultiple(array $values, int $ttl = 0)
    {
        $result = \wincache_ucache_set($values, null, $ttl);

        return !($result === false || \count($result));
    }
}