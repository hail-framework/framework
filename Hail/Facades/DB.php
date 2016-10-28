<?php
namespace Hail\Facades;

use Hail\DB\Medoo;

class DB extends Facade
{
	protected static function instance()
	{
		return new Medoo(
			Config::get('database')
		);
	}
}