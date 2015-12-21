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
		require HAIL_PATH . 'DI.php';

		return new DI([
			'embedded' => function ($c) {
				require HAIL_PATH . 'Cache/Embedded.php';
				return new Cache\Embedded(
					EMBEDDED_CACHE_ENGINE
				);
			},

			'config' => function ($c) {
				require HAIL_PATH . 'Config.php';
				return new Config($c);
			},

			'loader' => function ($c) {
				require HAIL_PATH . 'Loader/PSR4.php';
				$loader = new Loader\PSR4($c);
				$loader->addPrefixes(
					$c['config']->get('env.autoload')
				);
				return $loader;
			},

			// After here, no need add require
			'alias' => function ($c) {
				return new Loader\Alias(
					$c['config']->get('env.alias')
				);
			},

			'cache' => function ($c) {
				return new Cache(
					$c['config']->get('app.cache')
				);
			},

			'router' => function ($c) {
				return new Router($c);
			},

			'gettext' => function ($c) {
				return new I18N\Gettext();
			},

			'request' => function ($c) {
				return Bootstrap::httpRequest();
			},

			'response' => function ($c) {
				return new Http\Response();
			},

			'event' => function ($c) {
				return new Event\Emitter();
			},

			'app' => function ($c) {
				return new Application();
			},

			'output' => function ($c) {
				return new Output();
			},

			'model' => function ($c) {
				return new Utils\ObjectFactory('Model');
			},

			'lib' => function ($c) {
				return new Utils\ObjectFactory('Library');
			},
		]);
	}

	public static function autoload($di)
	{
		$di['loader']->register();
		$di['alias']->register();
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