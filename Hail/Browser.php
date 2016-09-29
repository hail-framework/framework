<?php

namespace Hail;

use Hail\Browser\Request;
use Hail\Browser\Request\Body;
use Hail\Utils\Json;

class Browser
{
	public function get($url, $params = [], $headers = [])
	{
		return Request::get($url, $headers, $params);
	}

	public function post($url, $params = [], $headers = [])
	{
		$body = Body::form($params);
		return Request::post($url, $headers, $body);
	}

	public function socket($url, $content)
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

	public function json($url, $params = [], $headers = [])
	{
		if (is_string($params)) {
			$body = $params;
		} else {
			$body = Body::json($params);
		}

		return Request::post($url, ['Content-Type' => 'application/json'] + $headers, $body);
	}

	public function head($url, $headers = [])
	{
		return Request::head($url, $headers);
	}

	public function patch($url, $headers = [], $body = null)
	{
		return Request::patch($url, $headers, $body);
	}

	public function put($url, $headers = [], $body = null)
	{
		return Request::put($url, $headers, $body);
	}

	public function delete($url, $headers = [], $body = null)
	{
		return Request::delete($url, $headers, $body);
	}

	public function timeout($seconds)
	{
		return Request::timeout($seconds);
	}
}
