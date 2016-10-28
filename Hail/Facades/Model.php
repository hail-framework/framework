<?php
namespace Hail\Facades;

use Hail\Utils\ObjectFactory;

class Model extends Facade
{
	protected static function instance()
	{
		return new ObjectFactory('App\\Model');
	}
}