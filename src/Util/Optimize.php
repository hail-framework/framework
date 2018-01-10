<?php

namespace Hail\Util;

/**
 * 使用 PHP 内存缓存代替类中的通过文件获取数据，用于性能最大化
 *
 * @package Hail\Cache
 */
class Optimize
{
    /**
     * @var string
     */
    private static $type;

    private static $cache;
    private static $set;
    private static $get;
    private static $multi = false;

    private static $engine = [
        'yac' => [
            'class' => \Yac::class,
            'set' => 'set',
            'get' => 'get',
            'multi' => true,
        ],
        'pcache' => [
            'set' => 'pcache_set',
            'get' => 'pcache_get',
        ],
        'wincache' => [
            'set' => 'wincache_ucache_set',
            'get' => 'wincache_ucache_get',
            'multi' => true,
        ],
        'apcu' => [
            'set' => 'apcu_store',
            'get' => 'apcu_fetch',
            'multi' => true,
        ],
    ];

    public static function init(string $ext = null): void
    {
        if ($ext === null) {
            $ext = \env('OPTIMIZE_ENGINE') ?? 'auto';
        }

        if ($ext === 'none') {
            self::$set = null;
            self::$get = null;

            return;
        }

        if (isset(self::$engine[$ext])) {
            $check = [$ext];
        } else {
            $check = \array_keys(self::$engine);
        }

        foreach ($check as $v) {
            if (\extension_loaded($v)) {
                self::$type = $v;
                break;
            }
        }

        if (self::$type === null) {
            return;
        }

        $def = self::$engine[self::$type];

        [
            'set' => $set,
            'get' => $get,
        ] = $def;

        if (isset($def['class'])) {
            self::$cache = new $def['class']();
            self::$set = [self::$cache, $set];
            self::$get = [self::$cache, $get];
        } else {
            self::$set = $set;
            self::$get = $get;
        }

        self::$multi = $def['multi'] ?? false;
    }

    /**
     * @param string $prefix
     * @param string $key
     * @param        $value
     *
     * @return mixed
     */
    public static function set(string $prefix, string $key, $value)
    {
        if (self::$set === null) {
            return false;
        }

        return (self::$set)(self::key($prefix, $key), $value);
    }

    /**
     * @param string $prefix
     * @param array  $array
     *
     * @return mixed
     */
    public static function setMultiple(string $prefix, array $array)
    {
        if (self::$set === null) {
            return false;
        }

        if (!self::$multi) {
            $return = true;
            foreach ($array as $k => $v) {
                if (false === self::set($prefix, $k, $v)) {
                    $return = false;
                }
            }

            return $return;
        }

        $list = [];
        foreach ($array as $k => $v) {
            $list[self::key($prefix, $k)] = $v;
        }

        return (self::$set)($list);
    }

    /**
     * @param string $prefix
     * @param string $key
     *
     * @return mixed
     */
    public static function get(string $prefix, string $key)
    {
        if (self::$get === null) {
            return false;
        }

        return (self::$get)(self::key($prefix, $key));
    }

    protected static function key(string $prefix, string $key): string
    {
        $key = "{$prefix}|{$key}";
        if (self::$type === 'yac' && \strlen($key) > \YAC_MAX_KEY_LEN) {
            $key = \sha1($key);
        }

        return $key;
    }
}