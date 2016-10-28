<?php
namespace Hail\Facades;

use Hail\Utils\ObjectFactory;

class Library extends Facade
{
	protected static $name = 'lib';

	protected static function instance()
	{
		return new ObjectFactory('App\\Library');
	}
}