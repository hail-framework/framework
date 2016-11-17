<?php
namespace Hail\Facades;

use Hail\DB;

/**
 * Class CachedDB
 *
 * @package Hail\Facades
 *
 * @method static mixed select(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 * @method static mixed get(array $struct, $fetch = \PDO::FETCH_ASSOC, $fetchArgs = null)
 * @method static DB\Cache expiresAfter(int $lifetime = 0)
 * @method static DB\Cache name(string $name)
 * @method static DB\Cache reset()
 * @method static bool delete(string $name, mixed $arguments = null)
 */
class CachedDB extends Facade
{
	protected static $name = 'cdb';

	protected static function instance()
	{
		return new DB\Cache();
	}
}