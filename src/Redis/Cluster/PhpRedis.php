<?php

namespace Hail\Redis\Cluster;

use Hail\Redis\Exception\RedisException;
use Hail\Redis\Traits\PhpRedisTrait;


class PhpRedis extends AbstractCluster
{
    use PhpRedisTrait;

    protected static $typeMap = [
        \RedisCluster::REDIS_NOT_FOUND => 'none',
        \RedisCluster::REDIS_STRING => 'string',
        \RedisCluster::REDIS_SET => 'set',
        \RedisCluster::REDIS_LIST => 'list',
        \RedisCluster::REDIS_ZSET => 'zset',
        \RedisCluster::REDIS_HASH => 'hash',
    ];

    protected $connected = false;

    /**
     * PhpRedis constructor.
     *
     * @param $config
     *
     * @throws RedisException
     */
    public function __construct($config)
    {
        parent::__construct($config);

        $this->connect();
    }

    public function connect()
    {
        if ($this->redis) {
            return;
        }

        $this->redis = new \RedisCluster(null, $this->hosts, $this->timeout ?: 0.0, $this->readTimeout ?: 0.0,
            (bool) $this->persistent);

        $this->connected = true;
        $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);

        /* password support after phpreids 3.2.0 release
        if ($password = $this->getPassword()) {
            $this->auth($password);
        }
        */
    }

    public function flushAll()
    {
        $nodes = $this->redis->_masters();

        foreach ($nodes as $node) {
            if (!$this->redis->flushAll($node)) {
                return false;
            }
        }

        return true;
    }

    public function flushDb()
    {
        $nodes = $this->redis->_masters();

        foreach ($nodes as $node) {
            if (!$this->redis->flushDB($node)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $name
     * @param $args
     *
     * @return null
     * @throws RedisException
     */
    public function __call($name, $args)
    {
        [$name, $args] = $this->normalize($name, $args);

        try {
            $response = $this->executeMethod($name, $args);
        } catch (\RedisException $e) {
            throw new RedisException($e->getMessage(), 0, $e);
        }

        return $this->parseResponse($name, $response);
    }
}
