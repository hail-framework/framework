<?php
namespace Hail;

/**
 * Class Bootstrap
 * @package Hail
 */
class Bootstrap
{
	private static $alias = [
		'db' => 'DB',
		'app' => 'Application',
		'lib' => 'Library',
	];

	public static function di($start = [])
	{
		require HAIL_PATH . 'Cache/EmbeddedTrait.php';
		require HAIL_PATH . 'DI.php';

		$set = [
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

			'alias' => function ($c) {
				return new Loader\Alias(
					self::alias($c)
				);
			},
		];

		$optional = self::diOptional();
		if (empty($start)) {
			$set = array_merge($set, $optional);
		} else {
			$set = [];
			foreach ($start as $v) {
				if (isset($optional[$v])) {
					$set[$v] = $optional[$v];
				}
			}
		}

		$di = new DI($set);

		$di['loader']->register();
		$di['alias']->register();
		\DI::swap($di);

		return $di;
	}

	private static function alias($di)
	{
		$keys = $di->keys();
		$alias = $di['config']->get('env.alias');
		$alias['DI'] = 'Hail\\Facades\\DI';
		foreach ($keys as $v) {
			$name = self::$alias[$v] ?? ucfirst($v);
			$alias[$name] = 'Hail\\Facades\\' . $name;
		}
		return $alias;
	}

	public static function diOptional()
	{
		return [
			'gettext' => function ($c) {
				return new I18N\Gettext();
			},

			'cache' => function ($c) {
				return new Cache(
					$c['config']->get('app.cache')
				);
			},

			'db' => function ($c) {
				return new DB\Medoo(
					$c['config']->get('app.database')
				);
			},

			'router' => function ($c) {
				return new Router($c);
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

			'acl' => function ($c) {
				return new Acl();
			},

			'model' => function ($c) {
				return new Utils\ObjectFactory('Model');
			},

			'lib' => function ($c) {
				return new Utils\ObjectFactory('Library');
			},

			'template' => function ($c) {
				return new Latte\Engine(
					$c['config']->get('app.template')
				);
			},
		];
	}

	public static function httpRequest()
	{
		$url = new Http\UrlScript();

		$method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null;
		if ($method === 'POST' && isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
			&& preg_match('#^[A-Z]+\z#', $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])
		) {
			$method = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		$remoteAddr = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
		$remoteHost = isset($_SERVER['REMOTE_HOST']) ? $_SERVER['REMOTE_HOST'] : null;

		return new Http\Request($url, $method, $remoteAddr, $remoteHost);
	}
}