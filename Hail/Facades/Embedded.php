<?php
namespace Hail\Facades;

class Embedded extends Facade
{
	protected static function instance()
	{
		return new \Hail\Cache\Embedded(
			EMBEDDED_CACHE_ENGINE
		);
	}
}