<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;

use Hail\Facades\{
	Strings,
	Arrays,
	Json
};


/**
 * HttpRequest provides access scheme for request sent via HTTP.
 *
 * @property array $inputs
 */
class Request
{
	const
		GET = 'GET',
		POST = 'POST',
		HEAD = 'HEAD',
		PUT = 'PUT',
		DELETE = 'DELETE',
		PATCH = 'PATCH',
		OPTIONS = 'OPTIONS';

	/** @var string */
	private $method;

	/** @var UrlScript|Url */
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
	private $allHeaders = false;

	/** @var null|string */
	private $content;

	/** @var string|NULL */
	private $remoteAddress;

	/** @var string|NULL */
	private $remoteHost;

	/** @var Input */
	public $input;

	public function __construct(Url $url, $method = null, $remoteAddress = null, $remoteHost = null, $input = null)
	{
		$this->url = $url;
		$this->method = $method ?: 'GET';
		$this->remoteAddress = $remoteAddress;
		$this->remoteHost = $remoteHost;

		$this->post = (array) $input['post'] ?? [];
		$this->files = (array) $input['files'] ?? [];
		$this->cookies = (array) $input['cookies'] ?? [];
		$this->headers = array_change_key_case((array) $input['headers'] ?? [], CASE_LOWER);

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
			return $this->input->getAll();
		}

		return null;
	}

	/**
	 * Returns URL object.
	 */
	public function getUrl(): Url
	{
		return $this->url;
	}

	/**
	 * Returns URL object.
	 */
	public function cloneUrl(): Url
	{
		return clone $this->url;
	}

	public function getPathInfo(): string
	{
		if ($this->url instanceof UrlScript) {
			return $this->url->getPathInfo();
		}

		return $this->url->getPath();
	}

	/********************* query, post, files & cookies ****************d*g**/

	public function input($key = null, $default = null)
	{
		return $this->input->get($key) ?? $default;
	}

	public function getInput()
	{
		return $this->input;
	}

	/**
	 * Returns variable provided to the script via URL query ($_GET).
	 * If no key is passed, returns the entire array.
	 *
	 * @return mixed
	 */
	public function getQuery(string $key = null)
	{
		return $this->url->getQueryParameter($key);
	}

	/**
	 * Returns variable provided to the script via POST method ($_POST).
	 * If no key is passed, returns the entire array.
	 *
	 * @return mixed
	 */
	public function getPost(string $key = null)
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
	 * @return FileUpload|array|NULL
	 */
	public function getFile(string $key = null)
	{
		return Helpers::getParam($this->files, '_FILES', $key);
	}

	/**
	 * Returns variable provided to the script via HTTP cookies.
	 *
	 * @return mixed
	 */
	public function getCookie(string $key = null)
	{
		return Helpers::getParam($this->cookies, '_COOKIE', $key);
	}

	/********************* method & headers ****************d*g**/


	/**
	 * Returns HTTP request method (GET, POST, HEAD, PUT, ...). The method is case-sensitive.
	 */
	public function getMethod(): string
	{
		return $this->method;
	}


	/**
	 * Checks if the request method is the given one.
	 */
	public function isMethod(string $method): bool
	{
		return strcasecmp($this->method, $method) === 0;
	}

	/**
	 * Return the value of the HTTP header. Pass the header name as the
	 * plain, HTTP-specified header name (e.g. 'Accept-Encoding').
	 */
	public function getHeader(string $header): ?string
	{
		$header = strtoupper($header);
		if ($this->allHeaders) {
			return $this->headers[$header] ?? null;
		} else if (array_key_exists($header, $this->headers)) {
			return $this->headers[$header] ?? null;
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
			}

			return $this->headers[$header] = null;
		}
	}


	/**
	 * Returns all HTTP headers.
	 */
	public function getHeaders(): array
	{
		if ($this->allHeaders) {
			return $this->headers;
		}

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

		$this->allHeaders = true;

		return $this->headers = $headers;
	}


	/**
	 * Returns referrer.
	 */
	public function getReferer(): ?Url
	{
		$referre = $this->getHeader('referer');

		return null === $referre ? null : new Url($referre);
	}


	/**
	 * Is the request is sent via secure channel (https).
	 */
	public function isSecured(): bool
	{
		return $this->url->getScheme() === 'https';
	}


	/**
	 * Is AJAX request?
	 */
	public function isAjax(): bool
	{
		return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
	}

	/**
	 * Determine if the request is the result of an PJAX call.
	 */
	public function isPjax(): bool
	{
		return $this->getHeader('X-PJAX') === 'true';
	}

	/**
	 * Determine if the request is sending JSON.
	 */
	public function isJson(): bool
	{
		return Strings::contains(
			$this->getHeader('CONTENT-TYPE') ?? '', ['/json', '+json']
		);
	}

	/**
	 * Determine if the current request probably expects a JSON response.
	 */
	public function expectsJson(): bool
	{
		return ($this->isAjax() && !$this->isPjax()) || $this->wantsJson();
	}

	/**
	 * Determine if the current request is asking for JSON in return.
	 */
	public function wantsJson(): bool
	{
		$acceptable = $this->getHeader('Accept');

		return $acceptable !== null && Strings::contains($acceptable, ['/json', '+json']);
	}

	/**
	 * Returns the IP address of the remote client.
	 */
	public function getRemoteAddress(): ?string
	{
		return $this->remoteAddress;
	}


	/**
	 * Returns the host of the remote client.
	 */
	public function getRemoteHost(): ?string
	{
		if ($this->remoteHost === null && $this->remoteAddress !== null) {
			$this->remoteHost = gethostbyaddr($this->remoteAddress);
		}

		return $this->remoteHost;
	}


	/**
	 * Returns raw content of HTTP request body.
	 */
	public function getRawBody(): ?string
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
