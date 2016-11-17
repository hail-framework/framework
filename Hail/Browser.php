<?php

namespace Hail;

use Hail\Browser\Request;
use Hail\Browser\Request\Body;
use Hail\Utils\Json;

class Browser
{
	/**
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 *
	 * @return Browser\Response
	 */
	public function get(string $url, array $params = [], array $headers = [])
	{
		return Request::get($url, $headers, $params);
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 *
	 * @return Browser\Response
	 */
	public function post(string $url, array $params = [], array $headers = [])
	{
		$body = Body::form($params);
		return Request::post($url, $headers, $body);
	}

	/**
	 * @param string $url
	 * @param string $content
	 *
	 * @return string
	 */
	public function socket(string $url, string $content)
	{
		$errno = 0;
		$errstr = '';

		$url = parse_url($url);
		$fp = fsockopen($url['host'], $url['port'], $errno, $errstr, 3);
		if (!$fp) {
			return Json::encode([
				'ret' => $errno,
				'msg' => $errstr,
			]);
		}

		fwrite($fp, $content . "\n");
		stream_set_timeout($fp, 3);
		$return = fgets($fp, 65535);
		fclose($fp);

		return $return;
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param array $headers
	 *
	 * @return Browser\Response
	 */
	public function json(string $url, array $params = [], array $headers = [])
	{
		if (is_string($params)) {
			$body = $params;
		} else {
			$body = Body::json($params);
		}

		return Request::post($url, ['Content-Type' => 'application/json'] + $headers, $body);
	}

	/**
	 * @param string $url
	 * @param array $headers
	 *
	 * @return Browser\Response
	 */
	public function head(string $url, array $headers = [])
	{
		return Request::head($url, $headers);
	}

	/**
	 * @param string $url
	 * @param array $headers
	 * @param string|null $body
	 *
	 * @return Browser\Response
	 */
	public function patch(string $url, array $headers = [], string $body = null)
	{
		return Request::patch($url, $headers, $body);
	}

	/**
	 * @param string $url
	 * @param array $headers
	 * @param string|null $body
	 *
	 * @return Browser\Response
	 */
	public function put(string $url, array $headers = [], string $body = null)
	{
		return Request::put($url, $headers, $body);
	}

	/**
	 * @param string $url
	 * @param array $headers
	 * @param string|null $body
	 *
	 * @return Browser\Response
	 */
	public function delete(string $url, $headers = [], string $body = null)
	{
		return Request::delete($url, $headers, $body);
	}

	/**
	 * @param int $seconds
	 *
	 * @return int
	 */
	public function timeout(int $seconds)
	{
		return Request::timeout($seconds);
	}
}
