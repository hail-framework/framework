<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modifiend by FlyingHail <flyinghail@msn.com>
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
