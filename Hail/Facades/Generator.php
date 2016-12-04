<?php
namespace Hail\Facades;

/**
 * Class Generator
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
class Generator extends Facade
{
	protected static $inDI = false;

	protected static function instance()
	{
		return \Hail\Utils\Generator::getInstance();
	}
}