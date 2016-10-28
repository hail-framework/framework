<?php
namespace Hail\Facades;

use Hail\Tracy\Bar\TracePanel;

class Trace extends Facade
{
	protected static function instance()
	{
		return new TracePanel(
			TEMP_PATH . 'xdebugTrace.xt'
		);
	}
}