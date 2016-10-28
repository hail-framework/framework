<?php
namespace Hail\Facades;

use Hail\Event\Emitter;

class Event extends Facade
{
	protected static function instance()
	{
		return new Emitter();
	}
}