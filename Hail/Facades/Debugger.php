<?php
namespace Hail\Facades;

class Debugger extends Facade
{
	protected static function instance()
	{
		return \Hail\Tracy\Debugger::getInstance();
	}

	public static function alias()
	{
		return \Hail\Tracy\Debugger::class;
	}
}