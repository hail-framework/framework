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
			},

			'Request' => function ($c) {
				return Bootstrap::httpRequest();
			},

			'Response' => function ($c) {
				return new Http\Response();
			},
		]);
	}

	public static function autoload($di)
	{
		$di['Loader']->register();
		$di['Alias']->register();
		\DI::swap($di);
	}

	public static function httpRequest()
	{
		$url = new Http\UrlScript();

		$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : NULL;
		if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
			&& preg_match('#^[A-Z]+\z#', $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
		) {
			$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		$remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : NULL;
		$remoteHost = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : NULL;

		return new Http\Request($url, $method, $remoteAddr, $remoteHost);
	}
}