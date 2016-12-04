<?php
namespace Hail\Facades;

/**
 * Class Serialize
 * @package Hail\Facades
 *
 */
class Generator extends Facade
{
	protected static $inDI = false;

	protected static function instance()
	{
		return \Hail\Utils\Generator::getInstance();
	}
}