<?php

namespace Hail\Util;

final class SafeStorage
{
    /**
     * @var string
     */
    private $hash;

    /**
     * @var array
     */
    private static $storage = [];

    public function __construct()
    {
        $this->hash = \spl_object_hash($this);
        self::$storage[$this->hash] = [];
    }

    public function set($key, $value): void
    {
        self::$storage[$this->hash][$key] = $value;
    }

    public function get($key)
    {
        return self::$storage[$this->hash][$key] ?? null;
    }

    public function __destruct()
    {
        unset(self::$storage[$this->hash]);
    }
}
