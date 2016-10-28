<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/7/5 0005
 * Time: 20:27
 */

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