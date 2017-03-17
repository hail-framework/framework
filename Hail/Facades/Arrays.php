<?php
namespace Hail\Facades;

use Hail\Util\ArrayDot;

/**
 * Class Arrays
 *
 * @package Hail\Facades
 *
 * @method static ArrayDot dot(array $array = [])
 * @method static mixed get(array $array, string $key = null)
 * @method static bool isAssoc(array $array)
 * @method static array filter(array $array)
 */
class Arrays extends Facade
{
	protected static $alias = \Hail\Util\Arrays::class;

	protected static function instance()
	{
		return new static::$alias;
	}
}