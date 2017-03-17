<?php
namespace Hail\Facades;

/**
 * Class Generators
 *
 * @package Hail\Facades
 *
 * @method static string random(int $length = 10, string $charList = '0-9a-zA-Z')
 * @method static string unique()
 * @method static string guid()
 * @method static string uuid3(string $namespace, string $name)
 * @method static string uuid4()
 * @method static string uuid5(string $namespace, string $name)
 * @method static bool isUUID(string $uuid)
 */
class Generators extends Facade
{
	protected static $alias = \Hail\Util\Generators::class;

	protected static function instance()
	{
		return new static::$alias;
	}
}