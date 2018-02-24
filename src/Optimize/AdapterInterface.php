<?php

namespace Hail\Optimize;


interface AdapterInterface
{
    public static function init(): bool;

    public static function get(string $key);

    public static function set(string $key, $value, int $ttl = 0);

    public static function setMultiple(array $values, int $ttl = 0);
}