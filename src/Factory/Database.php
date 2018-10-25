<?php

namespace Hail\Factory;

use Hail\Cache\{
    CacheItemPoolInterface, Simple\CacheInterface
};
use Hail\Database\{
    Database as DB, Develop,
    Cache\Cache, Cache\SimpleCache, Cache\CachedDBInterface
};
use Hail\Debugger\Debugger;

class Database extends AbstractFactory
{
    /**
     * @param array $config
     *
     * @return DB
     */
    public static function pdo(array $config = []): DB
    {
        [$hash, $config] = static::getKey($config, 'database');

        if (isset(static::$pool[$hash])) {
            return static::$pool[$hash];
        }

        $driver = $config['driver'] ?? '';

        if ($driver === 'develop' && !Debugger::$productionMode) {
            return static::$pool[$hash] = new Develop($config);
        }

        return static::$pool[$hash] = new DB($config);
    }

    public static function cache(DB $db, $cache): CachedDBInterface
    {
        if ($cache instanceof CacheInterface) {
            return new SimpleCache($db, $cache);
        }

        if ($cache instanceof CacheItemPoolInterface) {
            return new Cache($db, $cache);
        }

        throw new \RuntimeException('Cache must be CacheItemPoolInterface or CacheInterface');
    }
}