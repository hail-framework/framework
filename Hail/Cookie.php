<?php

namespace Hail;

use Hail\Facades\{
	Request,
	Response
};

/**
 * Class Cookie
 *
 * @package Hail
 */
class Cookie
{
	public $prefix = '';
	public $domain = '';
	public $path = '/';
	public $secure = false;
	public $httpOnly = true;
	public $lifetime = 0;

	public function __construct(array $config = [])
	{
		$this->prefix = $config['prefix'] ?? '';
		$this->domain = $config['domain'] ?? '';
		$this->path = $config['path'] ?? '/';
		$this->secure = $config['secure'] ?? false;
		$this->httpOnly = $config['httponly'] ?? true;
		$this->lifetime = $config['lifetime'] ?? true;
	}

	/**
	 * @param $name
	 * @param $value
	 * @param string|int|\DateTime $time
	 *
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function set($name, $value, $time = null)
	{
		Response::setCookie(
			$this->prefix . $name, $value,
			$time ?? $this->lifetime,
			$this->path,
			$this->domain,
			$this->secure,
			$this->httpOnly
		);
	}

	/**
	 * @param $name
	 *
	 * @return mixed
	 */
	public function get($name)
	{
		return Request::getCookie($this->prefix . $name);
	}

	/**
	 * @param $name
	 *
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function delete($name)
	{
		Response::deleteCookie(
			$this->prefix . $name,
			$this->path,
			$this->domain,
			$this->secure
		);
	}
}
