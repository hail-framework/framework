<?php

namespace Hail;

use Hail\{
	Facade\Facade, Container\Compiler, Tracy\Debugger
};
use Psr\Container\ContainerInterface;

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

		if ((ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING) !== 0) {
			ini_set('mbstring.func_overload', '0');
		}

		if (mb_internal_encoding() !== 'UTF-8') {
			mb_internal_encoding('UTF-8');
		}

		$container = static::container();

		Facade::setContainer($container);

		$container->get('alias')->register();

		$config = $container->get('config');
		date_default_timezone_set(
			$config->get('app.timezone')
		);

		if ($config->get('env.debug')) {
			$debugMode = Debugger::DETECT;
		} else {
			$debugMode = Debugger::PRODUCTION;
		}

		$container->get('debugger')->enable(
			$debugMode,
			STORAGE_PATH . 'log/'
		);

		static::i18n($container);

		self::$inited = true;
	}

	protected static function container(): ContainerInterface
	{
		$file = Compiler::$file;
		if (@filemtime($file) < Config::filemtime('container')) {
			(new Compiler())->compile();

			if (function_exists('opcache_invalidate')) {
				opcache_invalidate($file, true);
			}
		}

		require $file;

		return new \Container();
	}

	protected static function i18n(ContainerInterface $container)
	{
		$config = $container->get('config');

		$locale = $config->get('app.i18n.locale');

		if (is_array($locale)) {
			$found = null;
			foreach ($locale as $k => $v) {
				switch ($k) {
					case 'input':
						$found = $container->get('request')->input($v);
						break;
					case 'cookie':
						$found = $container->get('request')->getCookie($v);
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

		$alias = $config->get('app.i18n.alias');
		if (!empty($alias)) {
			$locale = $alias[explode('_', $locale)[0]] ?? $locale;
		}

		$container->get('i18n')->init(
			BASE_PATH . 'lang',
			$config->get('app.i18n.domain'),
			$locale
		);
	}
}