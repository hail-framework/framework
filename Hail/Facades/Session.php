<?php
namespace Hail\Facades;


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