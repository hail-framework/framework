<?php
namespace Hail\Facades;


class Acl extends Facade
{
	protected static function instance()
	{
		return new \Hail\Acl();
	}
}