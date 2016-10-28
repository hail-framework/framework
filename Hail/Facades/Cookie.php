<?php
namespace Hail\Facades;


class Cookie extends Facade
{
	protected static function instance()
	{
		return new \Hail\Cookie(
			Config::get('cookie')
		);
	}
}