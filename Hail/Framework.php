<?php

namespace Hail;

use Hail\{
	Facade\Facade,
	Container\Compiler,
	Container\Container,
	Tracy\Debugger
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
 *
 * @method static Application app()
 * @method static Container di()
 * @method static Config config()
 * @method static AliasLoader alias()
 * @method static Router router()
 * @method static I18N\I18N i18n()
 * @method static Http\ServerRequest request()
 * @method static Event\EventManager event()
 * @method static Output output()
 * @method static Latte\Engine template()
 * @method static Database\Database db()
 * @method static Acl acl()
 * @method static Session session()
 * @method static Cookie cookie()
 * @method static SimpleCache\CacheInterface cache()
 * @method static Cache\CacheItemPoolInterface cachePool()
 * @method static Database\Cache cdb()
 * @method static Browser browser()
 * @method static Filesystem\MountManager filesystem()
 * @method static Tracy\Debugger debugger()
 * @method static Http\Dispatcher dispatcher()
 */
class Framework
{
	protected static $inited = false;

	/**
	 * @var Container
	 */
	protected static $container;

	public static function init()
	{
		if (self::$inited === true) {
			throw new \LogicException('Framework can not init twice');
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

		$container = static::getContainer();

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

		static::i18nInit($container);

		self::$inited = true;
	}

	public static function getContainer(): Container
	{
		if (static::$container === null) {
			$file = Compiler::FILE;
			if (@filemtime($file) < Config::filemtime('container')) {
				(new Compiler())->compile();

				if (function_exists('opcache_invalidate')) {
					opcache_invalidate($file, true);
				}
			}

			require $file;

			static::$container = new \Container();
		}

		return static::$container;
	}

	public static function __callStatic($name, $arguments)
	{
		if (!static::$inited) {
			self::init();
		}

		static::$container->get(strtolower($name));
	}

	protected static function i18nInit(Container $container)
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