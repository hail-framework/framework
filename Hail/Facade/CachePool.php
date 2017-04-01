<?php
namespace Hail\Facade;

use Hail\Factory\Cache;

/**
 * Class Cache
 *
 * @package Hail\Facade
 *
 * @method static void setNamespace(string $namespace)
 * @method static string getNamespace()
 * @method static int ttl(null|int|\DateInterval $ttl)
 * @method static mixed get(string $key, $default = null)
 * @method static array getMultiple(array $keys, $default = null)
 * @method static bool has(string $key)
 * @method static bool setMultiple(iterable $values, null|int|\DateInterval $ttl = null)
 * @method static bool set(string $key, mixed $values, null|int|\DateInterval $ttl = null)
 * @method static bool delete(string $key)
 * @method static bool deleteMultiple(array $keys)
 * @method static bool clear()
 * @method static bool deleteAll()
 */
class CachePool extends Facade
{
	protected static $name = 'cachePool';

	protected static function instance()
	{
		return Cache::pool();
	}
}