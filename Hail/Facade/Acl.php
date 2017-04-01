<?php
namespace Hail\Facade;


/**
 * Class Acl
 *
 * @package Hail\Facade
 */
class Acl extends Facade
{
	protected static function instance()
	{
		return new \Hail\Acl();
	}
}