<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;

use Hail\Utils\{
	Arrays,
	Strings,
	Json
};


/**
 * HttpRequest provides access scheme for request sent via HTTP.
 *
 * @property array $inputs
 */
class Request
{
	/** @var string */
	private $method;

	/** @var UrlScript */
	private $url;

	/** @var array */
	private $post = [];

	/** @var array */
	private $json = [];

	/** @var array */
	private $files = [];

	/** @var array */
	private $cookies = [];

	/** @var array */
	private $headers = [];

	/** @var null|string */
	private $content;

	/** @var string|NULL */
	private $remoteAddress;

	/** @var string|NULL */
	private $remoteHost;

	/** @var Input */
	public $input;

	public function __construct(Url $url, $method = null, $remoteAddress = null, $remoteHost = null)
	{
		$this->url = $url;
		$this->method = $method ?: 'GET';
		$this->remoteAddress = $remoteAddress;
		$this->remoteHost = $remoteHost;

		$this->input = new Input($this);
	}

	public function __set($name, $value)
	{
		if ($name === 'inputs') {
			$this->input->setAll($value);
		}
	}

	public function __get($name)
	{
		if ($name === 'inputs') {
			$this->input->getAll();
		}

		return null;
	}

	/**
	 * Returns URL object.
	 *
	 * @return Url
	 */
	public function getUrl()
	{
		return $this->url;
	}

	/**
	 * Returns URL object.
	 *
	 * @return Url
	 */
	public function cloneUrl()
	{
		return clone $this->url;
	}

	/**
	 * @return string
	 */
	public function getPathInfo()
	{
		return $this->url->getPathInfo();
	}

	/********************* query, post, files & cookies ****************d*g**/

	public function input($key = null, $default = null)
	{
		return $this->input->get($key, $default);
	}

	/**
	 * Returns variable provided to the script via URL query ($_GET).
	 * If no key is passed, returns the entire array.
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function getQuery($key = null)
	{
		return $this->url->getQueryParameter($key);
	}

	/**
	 * Returns variable provided to the script via POST method ($_POST).
	 * If no key is passed, returns the entire array.
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function getPost($key = null)
	{
		return Helpers::getParam($this->post, '_POST', $key);
	}

	public function getJson($key = null)
	{
		if ($this->json === null) {
			$body = $this->getRawBody();
			$this->json = Json::decode($body);
		}

		if ($key === null) {
			return $this->json;
		} else {
			return Arrays::get($this->json, $key);
		}
	}

	/**
	 * Returns uploaded file.
	 *
	 * @param  string $key
	 *
	 * @return FileUpload|NULL
	 */
	public function getFile($key = null)
	{
		return Helpers::getParam($this->files, '_FILES', $key);
	}

	/**
	 * Returns variable provided to the script via HTTP cookies.
	 *
	 * @param  string|NULL $key
	 *
	 * @return mixed
	 */
	public function getCookie($key = null)
	{
		return Helpers::getParam($this->cookies, '_COOKIE', $key);
	}

	/********************* method & headers ****************d*g**/


	/**
	 * Returns HTTP request method (GET, POST, HEAD, PUT, ...). The method is case-sensitive.
	 *
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}


	/**
	 * Checks if the request method is the given one.
	 *
	 * @param  string
	 *
	 * @return bool
	 */
	public function isMethod($method)
	{
		return strcasecmp($this->method, $method) === 0;
	}

	/**
	 * Return the value of the HTTP header. Pass the header name as the
	 * plain, HTTP-specified header name (e.g. 'Accept-Encoding').
	 *
	 * @param  string $header
	 * @param  mixed $default
	 *
	 * @return mixed
	 */
	public function getHeader($header, $default = null)
	{
		$header = strtoupper($header);
		if (isset($this->headers[$header])) {
			return $this->headers[$header] === false ? $default : $this->headers[$header];
		}

		$key = 'HTTP_' . str_replace('-', '_', $header);
		if (isset($_SERVER[$key])) {
			return $this->headers[$header] = $_SERVER[$key];
		} else {
			$contentHeaders = [
				'CONTENT-LENGTH' => 'CONTENT_LENGTH',
				'CONTENT-MD5' => 'CONTENT_MD5',
				'CONTENT-TYPE' => 'CONTENT_TYPE',
			];

			if (isset($contentHeaders[$header], $_SERVER[$contentHeaders[$header]])) {
				return $this->headers[$header] = $_SERVER[$contentHeaders[$header]];
			} else {
				$this->headers[$header] = false;

				return $default;
			}
		}
	}


	/**
	 * Returns all HTTP headers.
	 *
	 * @return array
	 */
	public function getHeaders()
	{
		if (function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
		} else {
			$headers = [];
			foreach ($_SERVER as $k => $v) {
				if (strpos($k, 'HTTP_') === 0) {
					$k = substr($k, 5);
				} elseif (strncmp($k, 'CONTENT_', 8)) {
					continue;
				}
				$headers[str_replace('_', '-', $k)] = $v;
			}
		}

		return $this->headers = $headers;
	}


	/**
	 * Returns referrer.
	 *
	 * @return Url|NULL
	 */
	public function getReferer()
	{
		$referre = $this->getHeader('referer');

		return null === $referre ? null : new Url($referre);
	}


	/**
	 * Is the request is sent via secure channel (https).
	 *
	 * @return bool
	 */
	public function isSecured()
	{
		return $this->url->getScheme() === 'https';
	}


	/**
	 * Is AJAX request?
	 *
	 * @return bool
	 */
	public function isAjax()
	{
		return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
	}

	/**
	 * Determine if the request is the result of an PJAX call.
	 *
	 * @return bool
	 */
	public function isPjax()
	{
		return $this->getHeader('X-PJAX') === 'true';
	}

	/**
	 * Determine if the request is sending JSON.
	 *
	 * @return bool
	 */
	public function isJson()
	{
		return Strings::contains(
			$this->getHeader('CONTENT-TYPE'), ['/json', '+json']
		);
	}

	/**
	 * Determine if the current request probably expects a JSON response.
	 *
	 * @return bool
	 */
	public function expectsJson()
	{
		return ($this->isAjax() && !$this->isPjax()) || $this->wantsJson();
	}

	/**
	 * Determine if the current request is asking for JSON in return.
	 *
	 * @return bool
	 */
	public function wantsJson()
	{
		$acceptable = $this->getHeader('Accept');

		return $acceptable !== null && Strings::contains($acceptable, ['/json', '+json']);
	}

	/**
	 * Returns the IP address of the remote client.
	 *
	 * @return string|NULL
	 */
	public function getRemoteAddress()
	{
		return $this->remoteAddress;
	}


	/**
	 * Returns the host of the remote client.
	 *
	 * @return string|NULL
	 */
	public function getRemoteHost()
	{
		if ($this->remoteHost === null && $this->remoteAddress !== null) {
			$this->remoteHost = gethostbyaddr($this->remoteAddress);
		}

		return $this->remoteHost;
	}


	/**
	 * Returns raw content of HTTP request body.
	 *
	 * @return string|NULL
	 */
	public function getRawBody()
	{
		if ($this->content === null) {
			$this->content = file_get_contents('php://input');
		}

		return $this->content;
	}


	/**
	 * Parse Accept-Language header and returns preferred language.
	 *
	 * @param  string[] supported languages
	 *
	 * @return string|NULL
	 */
	public function detectLanguage(array $langs)
	{
		$header = $this->getHeader('Accept-Language');
		if (!$header) {
			return null;
		}

		$s = strtolower($header);  // case insensitive
		$s = str_replace('_', '-', $s);  // cs_CZ means cs-CZ
		rsort($langs);             // first more specific
		preg_match_all('#(' . implode('|', $langs) . ')(?:-[^\s,;=]+)?\s*(?:;\s*q=([0-9.]+))?#', $s, $matches);

		if (!$matches[0]) {
			return null;
		}

		$max = 0;
		$lang = null;
		foreach ($matches[1] as $key => $value) {
			$q = $matches[2][$key] === '' ? 1.0 : (float) $matches[2][$key];
			if ($q > $max) {
				$max = $q;
				$lang = $value;
			}
		}

		return $lang;
	}

}
