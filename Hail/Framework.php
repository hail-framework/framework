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
		if (self::$inited) {
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

		if ($timezone = $config->get('app.timezone')) {
            date_default_timezone_set($timezone);
        }

		if ($config->get('env.debug')) {
			$debugMode = Debugger::DETECT;
		} else {
			$debugMode = Debugger::PRODUCTION;
		}

		Debugger::enable(
			$debugMode,
			STORAGE_PATH . 'log/'
		);

		self::$inited = true;
	}

	public static function getContainer(): Container
	{
		if (static::$container === null) {
            $file = RUNTIME_PATH . 'Container.php';

            if (@filemtime($file) < Config::filemtime('container')) {
                $compiler = new Compiler(
                    Config::load('container')
                );

                file_put_contents($file, $compiler->compile());

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
}