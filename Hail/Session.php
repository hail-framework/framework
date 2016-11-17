<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/15 0015
 * Time: 17:11
 */

namespace Hail;

/**
 * Class Session
 *
 * @package Hail
 */
class Session implements \ArrayAccess
{
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

		if (in_array($config['handler'], ['DB', 'Embedded', 'Redis'], true)) {
			$handlerClass = 'Hail\\Session\\' . $config['handler'] . 'Handler';

			$settings = $config['settings'] ?? [];
			if (!isset($settings['lifetime']) && isset($this->cookieParams['lifetime'])) {
				$settings['lifetime'] =  $this->cookieParams['lifetime'];
			}
			$this->handler = new $handlerClass($settings);
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
		return $this->offsetGet($key);
	}

	public function has($key)
	{
		return $this->offsetExists($key);
	}

	public function set($key, $value)
	{
		return $this->offsetSet($key, $value);
	}

	public function delete($key)
	{
		return $this->offsetUnset($key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetExists($offset)
	{
		return isset($_SESSION[$offset]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetGet($offset)
	{
		return $_SESSION[$offset] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetSet($offset, $value)
	{
		$_SESSION[$offset] = $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function offsetUnset($offset)
	{
		unset($_SESSION[$offset]);
	}
}