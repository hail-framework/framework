<?php
namespace Hail\Facades;

/**
 * Class Cookie
 *
 * @package Hail\Facades
 *
 * @method static set(string $name, mixed $value, string|int|\DateTime $time = 0)
 * @method static mixed get(string $name)
 * @method static delete(string $name)
 */
class Cookie extends Facade
{
	protected static function instance()
	{
		return new \Hail\Cookie(
			Config::get('cookie')
		);
	}
}