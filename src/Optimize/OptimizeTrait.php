<?php

namespace Hail\Optimize;

trait OptimizeTrait
{
    /**
     * @var Optimize
     */
    private static $__optimizeInstance;

    protected static function optimizeInstance(Optimize $object = null): Optimize
    {
        return self::$__optimizeInstance = $object ?? Optimize::getInstance();
    }

    /**
     * @param string            $key
     * @param string|array|null $file
     *
     * @return mixed
     */
    protected static function optimizeGet(string $key, $file = null)
    {
        $object = self::$__optimizeInstance ?? self::optimizeInstance();

        return $object->get(static::class, $key, $file);
    }

    /**
     * @param string|array      $key
     * @param mixed             $value
     * @param string|array|null $file
     *
     * @return mixed
     */
    protected static function optimizeSet($key, $value = null, $file = null)
    {
        $object = self::$__optimizeInstance ?? self::optimizeInstance();

        return $object->set(static::class, $key, $value, $file);
    }
}