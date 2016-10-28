<?php
namespace Hail\Facades;


class Response extends Facade
{
	protected static function instance()
	{
		return new \Hail\Http\Response();
	}
}