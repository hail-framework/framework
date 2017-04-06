<?php
namespace Hail;

use Hail\Tracy\Debugger;
use Hail\Facade\{
	Config, Alias, I18N, Request
};

if (!defined('BASE_PATH')) {
	throw new \LogicException('Must defined the application base folder');
}

// System Start Time
defined('START_TIME') || define('START_TIME', $_SERVER['REQUEST_TIME_FLOAT']);

// Now timestamp
defined('NOW') || define('NOW', $_SERVER['REQUEST_TIME']);

// Absolute path to the hail-framework base folder
defined('HAIL_PATH') || define('HAIL_PATH', substr(__DIR__, 0, -4));

// Absolute path to the storage folder
defined('STORAGE_PATH') || define('STORAGE_PATH', BASE_PATH . 'storage' . DIRECTORY_SEPARATOR);
defined('RUNTIME_PATH') || define('RUNTIME_PATH', STORAGE_PATH . 'runtime' . DIRECTORY_SEPARATOR);

if (__DIR__ !== HAIL_PATH . 'Hail') {
	throw new \LogicException('HAIL_PATH is set to a wrong directory');
}

require __DIR__ . '/helpers.php';

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

		if (!extension_loaded('mbstring')) {
			throw new \RuntimeException('Must be enabled mbstring extension');
		}

		if (mb_internal_encoding() !== 'UTF-8') {
			mb_internal_encoding('UTF-8');
		}

		if ((ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING) !== 0) {
			ini_set('mbstring.func_overload', '0');
		}

		Alias::register();

		date_default_timezone_set(
			Config::get('app.timezone')
		);

		if (Config::get('env.debug')) {
			$debugMode = Debugger::DETECT;
		} else {
			$debugMode = Debugger::PRODUCTION;
		}

		Debugger::enable(
			$debugMode,
			STORAGE_PATH . 'log/'
		);

		static::i18n();

		self::$inited = true;
	}

	protected static function i18n()
	{
		$locale = Config::get('app.i18n.locale');

		if (is_array($locale)) {
			$found = null;
			foreach ($locale as $k => $v) {
				switch ($k) {
					case 'input':
						$found = Request::input($v);
						break;
					case 'cookie':
						$found = Request::getCookie($v);
						break;
					case 'default':
						$found = $v;
						break;
				}

				if ($found) {
					break;
				}
			}

			$locale = $found;
		}

		$locale = str_replace('-', '_', $locale);

		$alias = Config::get('app.i18n.alias');
		if (!empty($alias)) {
			$locale = $alias[explode('_', $locale)[0]] ?? $locale;
		}

		I18N::init(
			BASE_PATH . 'lang',
			Config::get('app.i18n.domain'),
			$locale
		);
	}
}