<?php
namespace Hail\Facades;

class Output extends Facade
{
	protected static function instance()
	{
		return new \Hail\Output();
	}
}