<?php
namespace Hail\Facade;

use Hail\Latte\Engine;

class Template extends Facade
{
	protected static function instance()
	{
		return new Engine(
			Config::get('template')
		);
	}
}