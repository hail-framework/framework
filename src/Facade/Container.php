<?php

namespace Hail\Facade;

/**
 * Class Container
 *
 * @package Hail\Facade
 *
 * @method static void set(string $name, mixed $value)
 * @method static mixed get(string $name)
 * @method static bool has(string $name)
 * @method static void delete(string $name)
 * @method static mixed call(callable $callback, array $map = [], array $params = null)
 * @method static mixed create($class, array $map = [], array $params = null)
 * @method static void inject($name, $value)
 * @method static void register($name, $func_or_map_or_type = null, $map = [])
 * @method static void alias(string $alias, string $name)
 * @method static void configure($name_or_func, $func_or_map = null, $map = [])
 * @method static mixed ref($name)
 */
class Container extends Facade
{
}