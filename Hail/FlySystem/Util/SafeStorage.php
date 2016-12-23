<?php

namespace Hail\Flysystem\Util;

final class SafeStorage
{
    /**
     * @var string
     */
    private $hash;

    /**
     * @var array
     */
    protected static $storage = [];

    public function __construct()
    {
        $this->hash = spl_object_hash($this);
        static::$storage[$this->hash] = [];
    }

    public function set($key, $value)
    {
        static::$storage[$this->hash][$key] = $value;
    }

    public function get($key)
    {
        return static::$storage[$this->hash][$key] ?? null;
    }

    public function __destruct()
    {
        unset(static::$storage[$this->hash]);
    }
}
