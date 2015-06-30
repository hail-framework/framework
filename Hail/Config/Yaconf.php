<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/6/30 0030
 * Time: 15:05
 */

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