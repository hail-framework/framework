<?php
namespace Hail\Config;

/**
 * Class Yaconf
 * @package Hail\Config
 */
class Yaconf
{
	/**
	 * Determine if the given configuration value exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function has($key)
	{
		return \Yaconf::has($key);
	}

	/**
	 * Get the specified configuration value.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		return \Yaconf::get($key, $default);
	}
}