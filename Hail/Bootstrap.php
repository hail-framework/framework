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
		// DETECTS URI, base path and script path of the request.
		$url = new Http\Url;
		$url->setScheme(!empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https' : 'http');
		$url->setUser(isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '');
		$url->setPassword(isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '');

		// host & port
		if ((isset($_SERVER[$tmp = 'HTTP_HOST']) || isset($_SERVER[$tmp = 'SERVER_NAME']))
			&& preg_match('#^([a-z0-9_.-]+|\[[a-f0-9:]+\])(:\d+)?\z#i', $_SERVER[$tmp], $pair)
		) {
			$url->setHost(strtolower($pair[1]));
			if (isset($pair[2])) {
				$url->setPort(substr($pair[2], 1));
			} elseif (isset($_SERVER['SERVER_PORT'])) {
				$url->setPort($_SERVER['SERVER_PORT']);
			}
		}

		// path & query
		if (isset($_SERVER['REQUEST_URI'])) {
			$path = $_SERVER['REQUEST_URI'];
			if (strpos($path, '?') !== false) {
				$path = strstr($_SERVER['REQUEST_URI'], '?', true);
			}
			if (strpos($path, '//') !== false) {
				$path = preg_replace('#/{2,}#', '/', $path);
			}
		} else {
			$path = '/';
		}
		$path = Http\Url::unescape($path, '%/?#');
		$path = htmlspecialchars_decode(
			htmlspecialchars($path, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES
		);
		$url->setPath($path);

		// detect script path
		if ($path !== '/') {
			$lpath = strtolower($path);
			$script = isset($_SERVER['SCRIPT_NAME']) ? strtolower($_SERVER['SCRIPT_NAME']) : '';
			if ($lpath !== $script) {
				$tmp = explode('/', $path);
				$script = explode('/', $script);
				$path = '';
				foreach (explode('/', $lpath) as $k => $v) {
					if ($v !== $script[$k]) {
						break;
					}
					$path .= $tmp[$k] . '/';
				}
			}
			$url->setScriptPath($path);
		}

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