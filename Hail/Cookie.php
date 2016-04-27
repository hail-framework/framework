<?php

namespace Hail;

/**
 * Class Cookie
 *
 * @package Hail
 */
class Cookie
{
	use DITrait;

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

	public function set($name, $value, $time = null)
	{
		$this->response->setCookie(
			$this->prefix . $name, $value,
			$time ?: $this->lifetime,
			$this->path,
			$this->domain,
			$this->secure,
			$this->httpOnly
		);
	}

	public function get($name)
	{
		return $this->request->getCookie($this->prefix . $name);
	}
}
