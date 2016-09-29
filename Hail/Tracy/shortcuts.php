<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

if (!function_exists('dump')) {
	/**
	 * Hail\Tracy\Debugger::dump() shortcut.
	 * @tracySkipLocation
	 */
	function dump($var)
	{
		array_map('Hail\Tracy\Debugger::dump', func_get_args());
		return $var;
	}
}

if (!function_exists('bdump')) {
	/**
	 * Hail\Tracy\Debugger::barDump() shortcut.
	 * @tracySkipLocation
	 */
	function bdump($var)
	{
		call_user_func_array('Hail\Tracy\Debugger::barDump', func_get_args());
		return $var;
	}
}
