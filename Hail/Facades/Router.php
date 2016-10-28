<?php
namespace Hail\Facades;


class Router extends Facade
{
	protected static function instance()
	{
		return new \Hail\Router(
			Config::get('route')
		);
	}
}