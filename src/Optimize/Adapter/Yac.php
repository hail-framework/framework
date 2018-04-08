<?php

namespace Hail\Optimize\Adapter;

\defined('YAC_EXTENSION') || \define('YAC_EXTENSION', \class_exists('\Yac'));

use Hail\Optimize\AdapterInterface;

class Yac implements AdapterInterface
{
    /**
     * @var \Yac
     */
    private $yac;

    private static $instance;

    public static function getInstance(array $config): ?AdapterInterface
    {
        if (!YAC_EXTENSION) {
            return null;
        }

        if (self::$instance === null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->yac = new \Yac();
    }

    public function get(string $key)
    {
        return $this->yac->get(
            self::key($key)
        );
    }

    public function set(string $key, $value, int $ttl = 0)
    {
        return $this->yac->set(self::key($key), $value, $ttl);
    }

    public function setMultiple(array $values, int $ttl = 0)
    {
        $list = [];
        foreach ($values as $k => $v) {
            $list[self::key($k)] = $v;
        }

        return $this->yac->set($list, $ttl);
    }

    private static function key($key)
    {
        if (\strlen($key) > \YAC_MAX_KEY_LEN) {
            return \sha1($key);
        }

        return $key;
    }
}