<?php
namespace Hail\Facades;

/**
 * Class Config
 *
 * @package Hail\Facades
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