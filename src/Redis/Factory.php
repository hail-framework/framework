<?php

namespace Hail\Redis;

\defined('PHP_REDIS_EXTENSION') || \define('PHP_REDIS_EXTENSION', \extension_loaded('redis'));
\defined('REDIS_CONNECT_POOL') || \define('REDIS_CONNECT_POOL', \class_exists('\redisProxy', false));

class Factory
{
    public static function client(array $config = []): RedisInterface
    {
        $driver = $config['driver'] ?? '';

        if ($driver === 'native' || !PHP_REDIS_EXTENSION) {
            return new Client\Native($config);
        }

        if ($driver === 'pool' && REDIS_CONNECT_POOL) {
            return new Client\ConnectPool($config);
        }

        return new Client\PhpRedis($config);
    }

    public static function cluster(array $config = []): RedisInterface
    {
        $driver = $config['driver'] ?? '';

        if ($driver === 'native' || !PHP_REDIS_EXTENSION) {
            return new Cluster\Native($config);
        }

        return new Cluster\PhpRedis($config);
    }
}