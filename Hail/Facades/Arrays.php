<?php
namespace Hail\Facades;

/**
 * Class Serialize
 *
 * @package Hail\Facades
 *
 * @method static \Hail\Utils\ArrayDot dot(array $array = [])
 * @method static mixed get(array $array, string $key = null)
 * @method static bool isAssoc(array $array)
 * @method static array filter(array $array)
 */
class Arrays extends Facade
{
	protected static $inDI = false;

	protected static function instance()
	{
		return \Hail\Utils\Arrays::getInstance();
	}
}