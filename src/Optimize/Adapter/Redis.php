<?php

namespace Hail\Optimize\Adapter;

use Hail\Optimize\AdapterInterface;
use Hail\Util\Serialize;

class Redis implements AdapterInterface
{
    /**
     * @var \Redis
     */
    private static $redis;

    public static function init(array $config): bool
    {
        if (!isset($config['redis']) || !\extension_loaded('redis')) {
            return false;
        }

        [$type, $redis] = \explode('://', $config['redis'], 2);

        if ($type !== 'unix' && $type !== 'tcp') {
            return false;
        }

        $arr = \explode('?', $redis, 2);
        $redis = $arr[0];

        $port = null;
        if ($type === 'tcp') {
            $tcp = \explode(':', $redis, 2);
            $redis = $tcp[0];
            $port = $tcp[1] ?? null;
        }

        self::$redis = new \Redis();

        if ($port === null) {
            $return = self::$redis->connect($redis);
        } else {
            $return = self::$redis->connect($redis, $port);
        }

        self::$redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        if ($return && isset($arr[1])) {
            $params = [];
            \parse_str($arr[1], $params);

            foreach ($params as $k => $v) {
                if ($k === 'auth' || $k === 'select') {
                    self::$redis->$k($v);
                }
            }
        }

        return $return;
    }

    public static function get(string $key)
    {
        $value = self::$redis->get($key);

        if ($value === false) {
            return false;
        }

        return Serialize::decode($value);
    }

    public static function setMultiple(array $values, int $ttl = 0)
    {
        if ($ttl > 0) {
            $success = true;
            foreach ($values as $key => $value) {
                if (!self::$redis->setEx($key, $ttl, $value)) {
                    $success = false;
                }
            }

            return $success;
        }

        return self::$redis->mSet($values);
    }

    public static function set(string $key, $value, int $ttl = 0)
    {
        return self::$redis->set($key, Serialize::encode($value), $ttl);
    }
}