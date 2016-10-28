<?php
namespace Hail\Facades;

class CachedDB extends Facade
{
	protected static $name = 'cdb';

	protected static function instance()
	{
		return new \Hail\DB\Cache();
	}
}