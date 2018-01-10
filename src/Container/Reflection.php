<?php

namespace Hail\Container;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Pseudo-namespace for some common reflection helper-functions.
 */
abstract class Reflection
{
    /**
     * Create a Reflection of the function references by any type of callable (or object implementing `__invoke()`)
     *
     * @param callable $callback
     *
     * @return ReflectionFunctionAbstract
     */
    public static function createFromCallable(callable $callback): ReflectionFunctionAbstract
    {
        switch (true) {
            case \is_array($callback):
                return new ReflectionMethod($callback[0], $callback[1]);

            case $callback instanceof Closure:
                return new ReflectionFunction($callback);

            case \is_object($callback):
                return new ReflectionMethod($callback, '__invoke');

            default:
                return new ReflectionFunction($callback);
        }
    }

    /**
     * Obtain the type-hint of a `ReflectionParameter`, but avoid triggering autoload (as a performance optimization)
     *
     * @param ReflectionParameter $param
     *
     * @return string|null fully-qualified type-name (or NULL, if no type-hint was available)
     */
    public static function getParameterType(ReflectionParameter $param): ?string
    {
        if (!$param->hasType()) {
            return null;
        }

        $type = $param->getType();

        if ($type === null || $type->isBuiltin()) {
            return null;
        }

        $type = $type->getName();
        $lower = \strtolower($type);

        if ($lower === 'self') {
            return $param->getDeclaringClass()->getName();
        }

        if ($lower === 'parent' && ($parent = $param->getDeclaringClass()->getParentClass())) {
            return $parent->getName();
        }

        return $type;
    }
}
