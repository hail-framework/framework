<?php
namespace Hail\Facades;

/**
 * Class Debugger
 *
 * @package Hail\Facades
 */
class Debugger extends Facade
{
	protected static $alias = \Hail\Tracy\Debugger::class;

	protected static function instance()
	{
		return new static::$alias;
	}
}