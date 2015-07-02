<?php
namespace Hail;

/**
 * Class Bootstrap
 * @package Hail
 */
class Bootstrap
{
	public static function di()
	{
		require HAIL_PATH . 'Cache/EmbeddedTrait.php';
		require HAIL_PATH . 'DI/Pimple.php';

		return new DI\Pimple([
			'EmbeddedCache' => function($c) {
				require HAIL_PATH . 'Cache/Embedded.php';

				return new Cache\Embedded(
					EMBEDDED_CACHE_ENGINE
				);
			},

			'Config' => function($c) {
				if (defined('CONFIG_ENABLE_YACONF') && CONFIG_ENABLE_YACONF &&
					extension_loaded('yaconf')
				) {
					require HAIL_PATH . 'Config/Yaconf.php';
					return new Config\Yaconf();
				} else {
					require HAIL_PATH . 'Config/Php.php';
					return new Config\Php($c);
				}
			},

			'Loader' => function($c) {
				require HAIL_PATH . 'Loader/PSR4.php';
				$loader = new Loader\PSR4($c);
				$loader->addPrefixes(
					$c['Config']->get('env.autoload')
				);
				return $loader;
			},

			// After here, no need add require
			'Alias' => function($c) {
				return new Loader\Alias(
					$c['Config']->get('env.alias')
				);
			},

			'Router' => function ($c) {
				return new Route\Tree();
			},

			'Gettext' => function($c) {
				return new I18N\Gettext();
			}
		]);
	}

	public static function autoload($di)
	{
		$di['Loader']->register();
		$di['Alias']->register();
		\DI::swap($di);
	}

	public static function path()
	{
		$path = str_replace(
			parse_url(
				\Config::get('app.url'),
				PHP_URL_PATH
			), '', REQUEST_URI
		);

		if ($path === '/index.php') {
			$path = '/';
		}

		return $path;
	}
}