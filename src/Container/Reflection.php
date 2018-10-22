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
        if (\is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        if (\is_object($callback)) {
            return new ReflectionMethod($callback, '__invoke');
        }

        return new ReflectionFunction($callback);
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

    /**
     * @param callable $callback
     *
     * @return array[]
     */
    public static function getParameters(callable $callback): array
    {
        $params = self::createFromCallable($callback)->getParameters();

        if ($params === []) {
            return [];
        }

        $return = [];
        foreach ($params as $param) {
            $array = [
                'name' => $param->name,
                'type' => self::getParameterType($param),
            ];

            if ($param->isOptional()) {
                $array['default'] = $param->getDefaultValue();
            } elseif ($array['type'] && $param->allowsNull()) {
                $array['default'] = null;
            }

            $reflection = $param->getDeclaringFunction();
            $array['file'] = $reflection->getFileName();
            $array['line'] = $reflection->getStartLine();

            $return[] = $array;
        }

        return $return;
    }
}
