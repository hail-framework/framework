<?php
namespace Hail\Facades;

class Console extends Facade
{
	protected static function instance()
	{
		return new \Hail\Console\Application(
			'Hail Framework',
			Config::get('env.version')
		);
	}
}