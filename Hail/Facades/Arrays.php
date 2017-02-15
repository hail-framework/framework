<?php
namespace Hail\Facades;

/**
 * Class Serialize
 *
 * @package Hail\Facades
 *
 * @method static \Hail\Util\ArrayDot dot(array $array = [])
 * @method static mixed get(array $array, string $key = null)
 * @method static bool isAssoc(array $array)
 * @method static array filter(array $array)
 */
class Arrays extends Facade
{
	protected static function instance()
	{
		return \Hail\Util\Arrays::getInstance();
	}

	public static function alias()
	{
		return \Hail\Util\Arrays::class;
	}
}