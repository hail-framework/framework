<?php
namespace Hail;

use Hail\Tracy\Debugger;

// System Start Time
define('START_TIME', $_SERVER['REQUEST_TIME_FLOAT']);

// Now timestamp
define('NOW', $_SERVER['REQUEST_TIME']);

// Absolute path to the temp folder
defined('TEMP_PATH') || define('TEMP_PATH', SYSTEM_PATH . 'temp/');

// Embedded cache engine: 'auto', 'yac', 'pcache', 'wincache', 'xcache', 'apcu', 'none'
defined('EMBEDDED_CACHE_ENGINE') || define('EMBEDDED_CACHE_ENGINE', 'auto');
defined('EMBEDDED_CACHE_CHECK_DELAY') || define('EMBEDDED_CACHE_CHECK_DELAY', 2);

/**
 * Class Bootstrap
 *
 * @package Hail
 */
class Bootstrap
{
	public static function init()
	{
		$di = Facades\DI::getInstance();

		$di['alias']->register();

		date_default_timezone_set(
			$di['config']->get('app.timezone')
		);

		if (PHP_SAPI !== 'cli' && $di['config']->get('env.debug')) {
			$debugMode = Debugger::DETECT;
		} else {
			$debugMode = Debugger::PRODUCTION;
		}

		Debugger::enable(
			$debugMode,
			TEMP_PATH . 'log/'
		);

		$config = $di['config']->get('app.i18n');
		$di['i18n']->init(
			SYSTEM_PATH . 'lang/',
			$config['domain'],
			$config['locale']
		);

		return $di;
	}
}