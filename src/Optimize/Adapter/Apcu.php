<?php

namespace Hail\Optimize\Adapter;

\defined('APCU_EXTENSION') || \define('APCU_EXTENSION', \extension_loaded('apcu'));

use Hail\Optimize\AdapterInterface;

class Apcu implements AdapterInterface
{
    private static $instance;

    public static function getInstance(array $config): ?AdapterInterface
    {
        if (!APCU_EXTENSION) {
            return null;
        }

        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function get(string $key)
    {
        return \apcu_fetch($key);
    }

    public function set(string $key, $value, int $ttl = 0)
    {
        return \apcu_store($key, $value, $ttl);
    }

    public function setMultiple(array $values, int $ttl = 0)
    {
        $result = \apcu_store($values, null, $ttl);

        return !($result === false || \count($result) > 0);
    }
}