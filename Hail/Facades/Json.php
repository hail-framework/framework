<?php
namespace Hail\Facades;

/**
 * Class Serialize
 * @package Hail\Facades
 *
 * @method static string encode($value, $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
 * @method static string decode(string $json, $asArray = true)
 */
class Json extends Facade
{
	protected static $inDI = false;

	protected static function instance()
	{
		return \Hail\Utils\Json::getInstance();
	}
}