<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;

/**
 * HttpRequest provides access scheme for request sent via HTTP.
 *
 */
class Request
{
	/** @internal */
	const CHARS = '\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}';

	/** @var string */
	private $method;

	/** @var UrlScript */
	private $url;

	/** @var array */
	private $post;

	/** @var array */
	private $files;

	/** @var array */
	private $cookies;

	/** @var array */
	private $headers;

	/** @var string|NULL */
	private $remoteAddress;

	/** @var string|NULL */
	private $remoteHost;

	public function __construct(Url $url, $method = NULL, $remoteAddress = NULL, $remoteHost = NULL)
	{
		$this->url = $url;
		$this->method = $method ?: 'GET';
		$this->remoteAddress = $remoteAddress;
		$this->remoteHost = $remoteHost;
	}

	/**
	 * Returns URL object.
	 * @return Url
	 */
	public function getUrl()
	{
		return $this->url;
	}

	/**
	 * Returns URL object.
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


	/**
	 * Returns variable provided to the script via URL query ($_GET).
	 * If no key is passed, returns the entire array.
	 * @param  string $key
	 * @return mixed
	 */
	public function getQuery($key = NULL)
	{
		return $this->url->getQueryParameter($key);
	}

	/**
	 * Returns variable provided to the script via POST method ($_POST).
	 * If no key is passed, returns the entire array.
	 * @param  string $key
	 * @return mixed
	 */
	public function getPost($key = NULL)
	{
		return Helpers::getParams($this->post, '_POST', $key);
	}

	/**
	 * Returns uploaded file.
	 * @param  string $key
	 * @return FileUpload|NULL
	 */
	public function getFile($key = NULL)
	{
		return Helpers::getParams($this->files, '_FILES', $key);
	}

	/**
	 * Returns variable provided to the script via HTTP cookies.
	 * @param  string|NULL $key
	 * @return mixed
	 */
	public function getCookie($key = NULL)
	{
		return Helpers::getParams($this->cookies, '_COOKIE', $key);
	}

	/********************* method & headers ****************d*g**/


	/**
	 * Returns HTTP request method (GET, POST, HEAD, PUT, ...). The method is case-sensitive.
	 * @return string
	 */
	public function getMethod()
	{
		return $this->method;
	}


	/**
	 * Checks if the request method is the given one.
	 * @param  string
	 * @return bool
	 */
	public function isMethod($method)
	{
		return strcasecmp($this->method, $method) === 0;
	}


	/**
	 * @deprecated
	 */
	public function isPost()
	{
		return $this->isMethod('POST');
	}


	/**
	 * Return the value of the HTTP header. Pass the header name as the
	 * plain, HTTP-specified header name (e.g. 'Accept-Encoding').
	 * @param  string $header
	 * @param  mixed $default
	 * @return mixed
	 */
	public function getHeader($header, $default = NULL)
	{
		$header = strtoupper($header);
		if (isset($this->headers[$header])) {
			return false === $this->headers[$header] ? $default : $this->headers[$header];
		}

		$key = 'HTTP_' . strtr($header, '-', '_');
		if (isset($_SERVER[$key])) {
			return $this->headers[$header] = $_SERVER[$key];
		} else {
			$contentHeaders = [
				'CONTENT-LENGTH' => 'CONTENT_LENGTH',
				'CONTENT-MD5' => 'CONTENT_MD5',
				'CONTENT-TYPE' => 'CONTENT_TYPE'
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
	 * @return array
	 */
	public function getHeaders()
	{
		if (function_exists('apache_request_headers')) {
			$headers = apache_request_headers();
		} else {
			$headers = array();
			foreach ($_SERVER as $k => $v) {
				if (strncmp($k, 'HTTP_', 5) == 0) {
					$k = substr($k, 5);
				} elseif (strncmp($k, 'CONTENT_', 8)) {
					continue;
				}
				$headers[ strtr($k, '_', '-') ] = $v;
			}
		}
		return $this->headers = $headers;
	}


	/**
	 * Returns referrer.
	 * @return Url|NULL
	 */
	public function getReferer()
	{
		$referre = $this->getHeader('referer');
		return NULL === $referre ? NULL : new Url($referre);
	}


	/**
	 * Is the request is sent via secure channel (https).
	 * @return bool
	 */
	public function isSecured()
	{
		return $this->url->getScheme() === 'https';
	}


	/**
	 * Is AJAX request?
	 * @return bool
	 */
	public function isAjax()
	{
		return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
	}


	/**
	 * Returns the IP address of the remote client.
	 * @return string|NULL
	 */
	public function getRemoteAddress()
	{
		return $this->remoteAddress;
	}


	/**
	 * Returns the host of the remote client.
	 * @return string|NULL
	 */
	public function getRemoteHost()
	{
		if ($this->remoteHost === NULL && $this->remoteAddress !== NULL) {
			$this->remoteHost = gethostbyaddr($this->remoteAddress);
		}
		return $this->remoteHost;
	}


	/**
	 * Returns raw content of HTTP request body.
	 * @return string|NULL
	 */
	public function getRawBody()
	{
		return file_get_contents('php://input');
	}


	/**
	 * Parse Accept-Language header and returns preferred language.
	 * @param  string[] supported languages
	 * @return string|NULL
	 */
	public function detectLanguage(array $langs)
	{
		$header = $this->getHeader('Accept-Language');
		if (!$header) {
			return NULL;
		}

		$s = strtolower($header);  // case insensitive
		$s = strtr($s, '_', '-');  // cs_CZ means cs-CZ
		rsort($langs);             // first more specific
		preg_match_all('#(' . implode('|', $langs) . ')(?:-[^\s,;=]+)?\s*(?:;\s*q=([0-9.]+))?#', $s, $matches);

		if (!$matches[0]) {
			return NULL;
		}

		$max = 0;
		$lang = NULL;
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
