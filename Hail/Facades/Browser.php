<?php

namespace Hail\Facades;


class Browser extends Facade
{
	protected static function instance()
	{
		return new \Hail\Browser();
	}
}