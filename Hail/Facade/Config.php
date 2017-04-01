<?php
namespace Hail\Facade;

/**
 * Class Config
 *
 * @package Hail\Facade
 *
 * @method static void set(string $key, mixed $value)
 * @method static mixed get(string $key, mixed $default = null)
 */
class Config extends Facade
{
	protected static function instance()
	{
		return new \Hail\Config();
	}
}