<?php

namespace Hail\Redis;

\defined('PHP_REDIS_EXTENSION') || \define('PHP_REDIS_EXTENSION', \extension_loaded('redis'));

class Factory
{
    /**
     * @param array $config
     *
     * @return RedisInterface
     * @throws Exception\RedisException
     */
    public static function client(array $config = []): RedisInterface
    {
        $driver = $config['driver'] ?? '';

        if ($driver === 'native' || !PHP_REDIS_EXTENSION) {
            return new Client\Native($config);
        }

        return new Client\PhpRedis($config);
    }

    /**
     * @param array $config
     *
     * @return RedisInterface
     * @throws Exception\RedisException
     */
    public static function cluster(array $config = []): RedisInterface
    {
        $driver = $config['driver'] ?? '';

        if ($driver === 'native' || !PHP_REDIS_EXTENSION) {
            return new Cluster\Native($config);
        }

        return new Cluster\PhpRedis($config);
    }
}