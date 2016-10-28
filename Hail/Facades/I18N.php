<?php
namespace Hail\Facades;

use Hail\I18N\Gettext;

class I18N extends Facade
{
	protected static function instance()
	{
		return new Gettext();
	}
}