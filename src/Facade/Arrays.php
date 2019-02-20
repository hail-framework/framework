<?php

namespace Hail\Facade;

/**
 * Class Arrays
 *
 * @package Hail\Facade
 * @see \Hail\Util\Arrays
 *
 * @method static array dot(array $array, string $prepend = '')
 * @method static mixed get(array $array, string $key = null, mixed $default = null)
 * @method static void set(array &$array, string $key, mixed $value)
 * @method static bool has(array $array, string ...$keys)
 * @method static void delete(array &$array, string ...$keys)
 * @method static bool isAssoc(array $array)
 * @method static array filter(array $array)
 * @method static int|string|null searchKey(array $arr, int|string $key)
 * @method static void insertBefore(array &$arr, int|string $key, array $inserted)
 * @method static void insertAfter(array &$arr, int|string $key, array $inserted)
 * @method static void renameKey(array &$arr, int|string $oldKey, int|string $newKey)
 * @method static array grep(array $arr, string $pattern, int $flags = 0)
 * @method static mixed pick(array &$arr, int|string $key)
 * @method static array|null shift(array &$array)
 */
class Arrays extends Facade
{
}