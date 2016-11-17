<?php
namespace Hail;

use Hail\Tracy\Debugger;
use Hail\Facades\{
	Config,
	Alias,
	I18N
};

// System Start Time
defined('START_TIME') || define('START_TIME', $_SERVER['REQUEST_TIME_FLOAT']);

// Now timestamp
defined('NOW') || define('NOW', $_SERVER['REQUEST_TIME']);

// Absolute path to the temp folder
defined('TEMP_PATH') || define('TEMP_PATH', SYSTEM_PATH . 'temp/');

/**
 * Class Bootstrap
 *
 * @package Hail
 */
class Bootstrap
{
	protected static $inited = false;

	public static function init()
	{
		if (self::$inited === true) {
			return;
		}

		$hailPath = substr(__DIR__, 0, -4);
		if (!defined('HAIL_PATH')) {
			define('HAIL_PATH', $hailPath);
		} elseif (HAIL_PATH !== $hailPath) {
			exit;
		}

		define('HAIL_SERIALIZE', Config::get('env.serialize'));

		Alias::register();

		date_default_timezone_set(
			Config::get('app.timezone')
		);

		if (PHP_SAPI !== 'cli' && Config::get('env.debug')) {
			$debugMode = Debugger::DETECT;
		} else {
			$debugMode = Debugger::PRODUCTION;
		}

		Debugger::enable(
			$debugMode,
			TEMP_PATH . 'log/'
		);

		I18N::init(
			SYSTEM_PATH . 'lang/',
			Config::get('app.i18n.domain'),
			Config::get('app.i18n.local')
		);

		self::$inited = true;
	}
}