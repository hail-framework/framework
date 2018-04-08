<?php

namespace Hail\Optimize\Adapter;

\defined('PHP_REDIS_EXTENSION') || \define('PHP_REDIS_EXTENSION', \extension_loaded('redis'));

use Hail\Optimize\AdapterInterface;
use Hail\Util\Serialize;

class Redis implements AdapterInterface
{
    /**
     * @var \Redis
     */
    private $redis;

    public static function getInstance(array $config): ?AdapterInterface
    {
        if (!PHP_REDIS_EXTENSION || !empty($config['redis'])) {
            return null;
        }

        try {
            return new static($config['redis']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function __construct(string $config)
    {
        [$type, $redis] = \explode('://', $config, 2);

        if ($type !== 'unix' && $type !== 'tcp') {
            throw new \InvalidArgumentException('Redis host invalid!');
        }

        $arr = \explode('?', $redis, 2);
        $redis = $arr[0];

        $port = null;
        if ($type === 'tcp') {
            $tcp = \explode(':', $redis, 2);
            $redis = $tcp[0];
            $port = $tcp[1] ?? null;
        }

        $this->redis = new \Redis();

        if ($port === null) {
            $return = $this->redis->connect($redis);
        } else {
            $return = $this->redis->connect($redis, $port);
        }

        if (!$return) {
            throw new \RuntimeException('Redis connect failed!');
        }

        $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        if (isset($arr[1])) {
            $params = [];
            \parse_str($arr[1], $params);

            foreach ($params as $k => $v) {
                if ($k === 'auth' || $k === 'select') {
                    $this->redis->$k($v);
                }
            }
        }
    }

    public function get(string $key)
    {
        $value = $this->redis->get($key);

        if ($value === false) {
            return false;
        }

        return Serialize::decode($value);
    }

    public function setMultiple(array $values, int $ttl = 0)
    {
        if ($ttl > 0) {
            $success = true;
            foreach ($values as $key => $value) {
                if (!$this->redis->setEx($key, $ttl, $value)) {
                    $success = false;
                }
            }

            return $success;
        }

        return $this->redis->mSet($values);
    }

    public function set(string $key, $value, int $ttl = 0)
    {
        return $this->redis->set($key, Serialize::encode($value), $ttl);
    }
}