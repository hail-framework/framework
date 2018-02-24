<?php

namespace Hail\Optimize\Adapter;


use Hail\Optimize\AdapterInterface;

class Yac implements AdapterInterface
{
    /**
     * @var \Yac
     */
    private static $yac;

    public static function init(): bool
    {
        if (!\class_exists('\Yac')) {
            return false;
        }

        self::$yac = new \Yac();

        return true;
    }

    public static function get(string $key)
    {
        return self::$yac->get(
            self::key($key)
        );
    }

    public static function set(string $key, $value, int $ttl = 0)
    {
        return self::$yac->set(self::key($key), $value, $ttl);
    }

    public static function setMultiple(array $values, int $ttl = 0)
    {
        $list = [];
        foreach ($values as $k => $v) {
            $list[self::key($k)] = $v;
        }

        return self::$yac->set($list, $ttl);
    }

    private static function key($key)
    {
        if (\strlen($key) > \YAC_MAX_KEY_LEN) {
            return \sha1($key);
        }

        return $key;
    }
}