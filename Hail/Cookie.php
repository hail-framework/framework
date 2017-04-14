<?php

namespace Hail;

use Hail\Facade\Request;

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
		$this->setCookie(
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
		return Request::getCookieParams()[$this->prefix . $name] ?? null;
	}

	/**
	 * @param $name
	 *
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function delete($name)
	{
		$this->deleteCookie(
			$this->prefix . $name,
			$this->path,
			$this->domain,
			$this->secure
		);
	}

	/**
	 * Sends a cookie.
	 * @param  string|int|\DateTimeInterface $time  expiration time, value 0 means "until the browser is closed"
	 * @return static
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function setCookie(string $name, string $value, $time, string $path = NULL, string $domain = NULL, bool $secure = NULL, bool $httpOnly = NULL, string $sameSite = NULL)
	{
		$sameSite = $sameSite ? "; SameSite=$sameSite" : '';
		$this->checkHeaders();
		setcookie(
			$name,
			$value,
			$time ? (int) $this->createDateTime($time)->format('U') : 0,
			($path ?? $this->path) . $sameSite,
			$domain ?? $this->domain,
			$secure ?? $this->secure,
			$httpOnly ?? $this->httpOnly
		);
		$this->removeDuplicateCookies();

		return $this;
	}

	/**
	 * Deletes a cookie.
	 *
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function deleteCookie(string $name, string $path = NULL, string $domain = NULL, bool $secure = NULL): void
	{
		$this->setCookie($name, '', 0, $path, $domain, $secure);
	}

	private function checkHeaders(): void
	{
		if (PHP_SAPI === 'cli') {
			return;
		}

		if (headers_sent($file, $line)) {
			throw new \RuntimeException('Cannot send header after HTTP headers have been sent' . ($file ? " (output started at $file:$line)." : '.'));
		}

		if (ob_get_length() && !array_filter(ob_get_status(true), function ($i) {
				return !$i['chunk_size'];
			})
		) {
			trigger_error('Possible problem: you are sending a HTTP header while already having some data in output buffer. Try Hail\Tracy\OutputDebugger or start session earlier.');
		}
	}

	/**
	 * Removes duplicate cookies from response.
	 *
	 * @internal
	 */
	private function removeDuplicateCookies(): void
	{
		if (headers_sent($file, $line) || ini_get('suhosin.cookie.encrypt')) {
			return;
		}

		$flatten = [];
		foreach (headers_list() as $header) {
			if (preg_match('#^Set-Cookie: .+?=#', $header, $m)) {
				$flatten[$m[0]] = $header;
				header_remove('Set-Cookie');
			}
		}
		foreach (array_values($flatten) as $key => $header) {
			header($header, $key === 0);
		}
	}

	/**
	 * DateTime object factory.
	 *
	 * @param  string|int|\DateTimeInterface
	 *
	 * @return \DateTimeInterface
	 */
	private function createDateTime($time)
	{
		if ($time instanceof \DateTimeInterface) {
			return new \DateTime($time->format('Y-m-d H:i:s'), $time->getTimezone());
		}

		if (is_numeric($time)) {
			// average year in seconds
			if ($time <= 31557600) {
				$time += time();
			}

			return new \DateTime('@' . $time,
				new \DateTimeZone(date_default_timezone_get())
			);
		}

		return new \DateTime($time);
	}
}
