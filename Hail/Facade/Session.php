<?php
namespace Hail\Facade;

/**
 * Class Session
 *
 * @package Hail\Facade
 *
 * @method static void regenerate()
 * @method static void id()
 * @method static void destroy()
 * @method static mixed get(string $key)
 * @method static bool has(string $key)
 * @method static void set(string $key, mixed $value)
 * @method static void delete(string $key)
 */
class Session extends Facade
{
	protected static function instance()
	{
		return new \Hail\Session(
			Config::get('session'),
			Config::get('cookie')
		);
	}
}