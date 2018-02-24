<?php

namespace Hail\Optimize;

use Hail\Optimize\Adapter\{
    Apcu, PCache, Redis, WinCache, Yac
};

/**
 * 缓存运算结果，用于性能最大化
 *
 * @package Hail\Cache
 */
class Optimize
{
    private const ADAPTERS = [
        'yac' => Yac::class,
        'apcu' => Apcu::class,
        'pcache' => PCache::class,
        'wincache' => WinCache::class,
        'redis' => Redis::class,
    ];

    /**
     * @var AdapterInterface|null
     */
    private static $adapter;

    /**
     * @var int
     */
    private static $expire;

    /**
     * @var int
     */
    private static $delay;

    public static function init(array $config): void
    {
        $adapter = $config['adapter'] ?? 'auto';
        if ($adapter === 'none') {
            return;
        }

        self::$expire = $config['expire'];
        self::$delay = $config['delay'];

        $adapters = [];
        if ($adapter === 'auto') {
            $adapters = self::ADAPTERS;
        } elseif (isset(self::ADAPTERS[$adapter])) {
            $adapters = [self::ADAPTERS[$adapter]];
        } elseif (\is_a($adapter, AdapterInterface::class, true)) {
            $adapters = [$adapter];
        }

        if ($adapters === []) {
            $adapters = self::ADAPTERS;
        }

        foreach ($adapters as $adapter) {
            if ($adapter::init($config)) {
                self::$adapter = $adapter;
                break;
            }
        }
    }

    /**
     * @param string $prefix
     * @param array  $array
     *
     * @return mixed
     */
    private static function setMultiple(string $prefix, array $array)
    {
        $list = [];
        foreach ($array as $k => $v) {
            $list["{$prefix}|{$k}"] = $v;
        }

        return self::$adapter::setMultiple($list, self::$expire);
    }


    private static function verifyMTime($file, array $check): bool
    {
        if (!\is_array($file)) {
            $file = [$file];
        } else {
            $file = \array_unique($file);
        }

        foreach ($file as $v) {
            if (\file_exists($v)) {
                if (!isset($check[$v]) || \filemtime($v) !== $check[$v]) {
                    return true;
                }
            } elseif (isset($check[$v])) {
                return true;
            }

            unset($check[$v]);
        }

        return [] !== $check;
    }

    private static function getMTime($file): array
    {
        $file = \array_unique((array) $file);

        $mtime = [];
        foreach ($file as $v) {
            if (\file_exists($v)) {
                $mtime[$v] = \filemtime($v);
            }
        }

        return $mtime;
    }

    public static function get(string $prefix, string $key, $file = null)
    {
        if (self::$adapter === null) {
            return false;
        }

        if (self::$delay > 0 && $file !== null) {
            $time = "{$prefix}|{$key}|time";
            $check = self::$adapter::get($time);
            $now = \time();
            if ($check !== false && $now >= ($check[0] + self::$delay)) {
                if (self::verifyMTime($file, $check[1])) {
                    return false;
                }

                $check[0] = $now;
                self::$adapter::set($time, $check, self::$expire);
            }
        }

        return self::$adapter::get("{$prefix}|{$key}");
    }

    public static function set(string $prefix, $key, $value, $file = null)
    {
        if ($file !== null) {
            $mtime = self::getMTime($file);
            if ($mtime !== []) {
                $key = [
                    $key => $value,
                    $key . '|time' => [\time(), $mtime],
                ];
            }
        }

        if (\is_array($key)) {
            return self::setMultiple(
                $prefix, $key
            );
        }

        return self::$adapter::set("{$prefix}|{$key}", $value, self::$expire);
    }
}

Optimize::init([
    'adapter' => \env('OPTIMIZE_ADAPTER') ?? 'auto',
    'expire' => (int) \env('OPTIMIZE_EXPIRE'),
    'delay' => (int) \env('OPTIMIZE_DELAY'),
    'redis' => \env('OPTIMIZE_REDIS'),
]);