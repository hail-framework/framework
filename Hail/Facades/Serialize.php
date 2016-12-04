<?php
namespace Hail\Facades;

/**
 * Class Serialize
 * @package Hail\Facades
 *
 * @method static string encode($value)
 * @method static string decode($value)
 * @method static string encodeToStr($value)
 * @method static string decodeFromStr($value)
 * @method static array encodeArray(array $array)
 * @method static array decodeArray(array $array)
 * @method static array encodeArrayToStr(array $array)
 * @method static array decodeArrayFromStr(array $array)
 * @method static \Hail\Utils\Serialize ext(string $type)
 * @method static \Hail\Utils\Serialize modify(string $type)
 */
class Serialize extends Facade
{
	protected static $inDI = false;

	protected static function instance()
	{
		return \Hail\Utils\Serialize::getInstance();
	}
}