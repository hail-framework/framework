<?php
namespace Hail\Facades;

/**
 * Class Cache
 *
 * @package Hail\Facades
 */
class Cache extends Facade
{
	protected static function instance()
	{
		return new \Hail\Cache(
			Config::get('cache')
		);
	}
}