<?php

/**
 * This file is part of the Tracy (https://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Tracy;


/**
 * Logger.
 */
interface LoggerInterface
{
	const
		DEBUG = Debugger::DEBUG,
		INFO = Debugger::INFO,
		WARNING = Debugger::WARNING,
		ERROR = Debugger::ERROR,
		EXCEPTION = Debugger::EXCEPTION,
		CRITICAL = Debugger::CRITICAL;

	function log($value, $priority = self::INFO);

}
