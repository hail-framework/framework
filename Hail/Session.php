<?php
namespace Hail;

use Hail\DB\Medoo;
use Hail\Facades\DB;
use Hail\Factory\{
	CacheFactory,
	RedisFactory
};
use Hail\Session\{
	CacheHandler,
	DBHandler,
	RedisHandler,
	SimpleCacheHandler
};
use Hail\Util\ArrayTrait;

/**
 * Class Session
 *
 * @package Hail
 */
class Session implements \ArrayAccess
{
	use ArrayTrait;

	private $cookieParams = [];
	private $handler;

	public function __construct(array $config, array $cookie = [])
	{
		if (is_array($cookie)) {
			$params = session_get_cookie_params();
			foreach ($params as $k => $v) {
				if (!isset($cookie[$k])) {
					$cookie[$k] = $v;
				}
			}
			$this->cookieParams = $cookie;
		}

		if ($config['handler']) {
			$connect = $config['connect'] ?? [];

			switch (strtolower($config['handler'])) {
				case 'redis':
					$class = RedisHandler::class;
					$conn = RedisFactory::get($connect);
					break;

				case 'simple':
				case 'simplecache':
					$class = SimpleCacheHandler::class;
					$conn = CacheFactory::simple($connect);
					break;

				case 'cache':
					$class = CacheHandler::class;
					$conn = CacheFactory::pool($connect);
					break;

				case 'db':
					$class = DBHandler::class;
					$conn = $connect ? new Medoo($connect) : DB::getInstance();
					break;

				default:
					$class = $conn = null;
			}

			if (null !== $class) {
				$settings = $config['settings'] ?? [];
				if (!isset($settings['lifetime']) && isset($this->cookieParams['lifetime'])) {
					$settings['lifetime'] = $this->cookieParams['lifetime'];
				}

				$this->handler = new $class($conn, $settings);
			}
		}

		$this->start();
	}

	private function start()
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			throw new \RuntimeException('session has already been started');
		}

		if ($this->handler) {
			session_set_save_handler($this->handler, true);
		}

		$params = $this->cookieParams;
		session_set_cookie_params(
			$params['lifetime'],
			$params['path'],
			$params['domain'],
			$params['secure'],
			$params['httponly']
		);

		$serializeHandler = ini_get('session.serialize_handler');
		if ($serializeHandler === 'php' || $serializeHandler === 'php_binary') {
			ini_set('session.serialize_handler', 'php_serialize');
		}

		session_start();
	}

	public function regenerate()
	{
		session_regenerate_id(true);
	}

	public function id()
	{
		return session_id();
	}

	public function destroy()
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			throw new \RuntimeException('session has not been started');
		}

		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				0,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}

		session_destroy();
	}

	public function get($key)
	{
		return $_SESSION[$key] ?? null;
	}

	public function has($key)
	{
		return isset($_SESSION[$key]);
	}

	public function set($key, $value)
	{
		$_SESSION[$key] = $value;
	}

	public function delete($key)
	{
		unset($_SESSION[$key]);
	}
}