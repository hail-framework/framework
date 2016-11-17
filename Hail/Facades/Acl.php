<?php
namespace Hail\Facades;


/**
 * Class Acl
 *
 * @package Hail\Facades
 */
class Acl extends Facade
{
	protected static function instance()
	{
		return new \Hail\Acl();
	}
}