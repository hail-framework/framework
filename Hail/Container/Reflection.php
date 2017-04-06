<?php

namespace Hail\Container;

use Closure;
use InvalidArgumentException;
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
	 * @type string pattern for parsing an argument type from a ReflectionParameter string
	 *
	 * @see Reflection::getParameterType()
	 */
	const ARG_PATTERN = '/(?:\<required\>|\<optional\>)\\s+([\\w\\\\]+)/';

	/**
	 * Create a Reflection of the function references by any type of callable (or object implementing `__invoke()`)
	 *
	 * @param callable $callback
	 *
	 * @return ReflectionFunctionAbstract
	 */
	public static function createFromCallable(callable $callback)
	{
		switch (true) {
			case $callback instanceof Closure:
				return new ReflectionFunction($callback);

			case is_object($callback):
				return new ReflectionMethod($callback, '__invoke');

			case is_array($callback):
				return new ReflectionMethod($callback[0], $callback[1]);

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
	public static function getParameterType(ReflectionParameter $param)
	{
		if (method_exists($param, 'getType')) {
			$type = $param->getType();

			return $type === null || $type->isBuiltin()
				? null // ignore scalar type-hints
				: $type->__toString();
		}

		if (preg_match(self::ARG_PATTERN, $param->__toString(), $matches) === 1) {
			return $matches[1];
		}

		return null; // no type-hint is available
	}
}
