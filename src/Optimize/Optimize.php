<?php

namespace Hail\Optimize;

use Hail\Optimize\Adapter\{
    Apcu, Redis, WinCache, Yac
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
        'wincache' => WinCache::class,
        'redis' => Redis::class,
    ];

    private static $instance;

    /**
     * @var AdapterInterface|null
     */
    private $adapter;

    /**
     * @var int
     */
    private $expire;

    /**
     * @var int
     */
    private $delay;

    public function __construct(array $config)
    {
        $adapter = $config['adapter'] ?? 'auto';
        if ($adapter === 'none') {
            return;
        }

        $this->expire = $config['expire'] ?? 0;
        $this->delay = $config['delay'] ?? 5;

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

        foreach ($adapters as $class) {
            $adapter = $class::getInstance($config);
            if ($adapter !== null) {
                $this->adapter = $adapter;
                break;
            }
        }
    }

    /**
     * @return static
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static([
                'adapter' => \env('OPTIMIZE_ADAPTER'),
                'expire' => (int) \env('OPTIMIZE_EXPIRE'),
                'delay' => (int) \env('OPTIMIZE_DELAY'),
                'redis' => \env('OPTIMIZE_REDIS'),
            ]);
        }

        return static::$instance;
    }

    /**
     * @param string $prefix
     * @param array  $array
     *
     * @return mixed
     */
    private function setMultiple(string $prefix, array $array)
    {
        if ($this->adapter === null) {
            return null;
        }

        $list = [];
        foreach ($array as $k => $v) {
            $list["{$prefix}|{$k}"] = $v;
        }

        return $this->adapter->setMultiple($list, $this->expire);
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

    public function get(string $prefix, string $key, $file = null)
    {
        if ($this->adapter === null) {
            return false;
        }

        if ($this->delay > 0 && $file !== null) {
            $time = "{$prefix}|{$key}|time";
            $check = $this->adapter->get($time);
            $now = \time();
            if ($check !== false && $now >= ($check[0] + $this->delay)) {
                if (self::verifyMTime($file, $check[1])) {
                    return false;
                }

                $check[0] = $now;
                $this->adapter->set($time, $check, $this->expire);
            }
        }

        return $this->adapter->get("{$prefix}|{$key}");
    }

    public function set(string $prefix, $key, $value, $file = null)
    {
        if ($this->adapter === null) {
            return null;
        }

        if ($file !== null) {
            $mtime = self::getMTime($file);
            if ($mtime !== []) {
                $key = [
                    $key => $value,
                    "{$key}|time" => [\time(), $mtime],
                ];
            }
        }

        if (\is_array($key)) {
            return $this->setMultiple(
                $prefix, $key
            );
        }

        return $this->adapter->set("{$prefix}|{$key}", $value, $this->expire);
    }
}