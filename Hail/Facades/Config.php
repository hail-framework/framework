<?php
namespace Hail\Facades;

class Config extends Facade
{
	protected static function instance()
	{
		return new \Hail\Config();
	}
}