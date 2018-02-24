<?php

namespace Hail\Optimize;

trait OptimizeTrait
{
    protected static function optimizeGet(string $key, $file = null)
    {
        return Optimize::get(static::class, $key, $file);
    }

    protected static function optimizeSet($key, $value = null, $file = null)
    {
        return Optimize::set(static::class, $key, $value, $file);
    }
}