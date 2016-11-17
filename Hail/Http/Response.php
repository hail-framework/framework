<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Hail\Http;

use Hail\Utils\DateTime;
use Hail\Utils\Generator;

/**
 * HttpResponse class.
 *
 */
class Response
{
	/** @var int cookie expiration: forever (23.1.2037) */
	const PERMANENT = 2116333333;

	/** @var int cookie expiration: until the browser is closed */
	const BROWSER = 0;

	/** HTTP 1.1 response code */
	const
		S100_CONTINUE = 100,
		S101_SWITCHING_PROTOCOLS = 101,
		S200_OK = 200,
		S201_CREATED = 201,
		S202_ACCEPTED = 202,
		S203_NON_AUTHORITATIVE_INFORMATION = 203,
		S204_NO_CONTENT = 204,
		S205_RESET_CONTENT = 205,
		S206_PARTIAL_CONTENT = 206,
		S300_MULTIPLE_CHOICES = 300,
		S301_MOVED_PERMANENTLY = 301,
		S302_FOUND = 302,
		S303_SEE_OTHER = 303,
		S303_POST_GET = 303,
		S304_NOT_MODIFIED = 304,
		S305_USE_PROXY = 305,
		S307_TEMPORARY_REDIRECT = 307,
		S400_BAD_REQUEST = 400,
		S401_UNAUTHORIZED = 401,
		S402_PAYMENT_REQUIRED = 402,
		S403_FORBIDDEN = 403,
		S404_NOT_FOUND = 404,
		S405_METHOD_NOT_ALLOWED = 405,
		S406_NOT_ACCEPTABLE = 406,
		S407_PROXY_AUTHENTICATION_REQUIRED = 407,
		S408_REQUEST_TIMEOUT = 408,
		S409_CONFLICT = 409,
		S410_GONE = 410,
		S411_LENGTH_REQUIRED = 411,
		S412_PRECONDITION_FAILED = 412,
		S413_REQUEST_ENTITY_TOO_LARGE = 413,
		S414_REQUEST_URI_TOO_LONG = 414,
		S415_UNSUPPORTED_MEDIA_TYPE = 415,
		S416_REQUESTED_RANGE_NOT_SATISFIABLE = 416,
		S417_EXPECTATION_FAILED = 417,
		S426_UPGRADE_REQUIRED = 426,
		S500_INTERNAL_SERVER_ERROR = 500,
		S501_NOT_IMPLEMENTED = 501,
		S502_BAD_GATEWAY = 502,
		S503_SERVICE_UNAVAILABLE = 503,
		S504_GATEWAY_TIMEOUT = 504,
		S505_HTTP_VERSION_NOT_SUPPORTED = 505;

	/** @var bool  Send invisible garbage for IE 6? */
	private static $fixIE = true;

	public $cookiePrefix = '';

	/** @var string The domain in which the cookie will be available */
	public $cookieDomain = '';

	/** @var string The path in which the cookie will be available */
	public $cookiePath = '/';

	/** @var bool Whether the cookie is available only through HTTPS */
	public $cookieSecure = false;

	/** @var bool Whether the cookie is hidden from client-side */
	public $cookieHttpOnly = true;

	/** @var bool Whether warn on possible problem with data in output buffer */
	public $warnOnBuffer = true;

	/** @var int HTTP response code */
	private $code = self::S200_OK;


	public function __construct()
	{
		if (is_int($code = http_response_code())) {
			$this->code = $code;
		}

		header_register_callback(
			['Hail\Http\Helpers', 'removeDuplicateCookies']
		);
	}

	public function disableWarnOnBuffer()
	{
		$this->warnOnBuffer = false;
	}

	/**
	 * Sets HTTP response code.
	 *
	 * @param  int
	 * @return self
	 * @throws \InvalidArgumentException  if code is invalid
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function setCode($code)
	{
		$code = (int) $code;
		if ($code < 100 || $code > 599) {
			throw new \InvalidArgumentException("Bad HTTP response '$code'.");
		}
		$this->checkHeaders();
		$this->code = $code;
		$protocol = isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.1';
		header($protocol . ' ' . $code, true, $code);
		return $this;
	}


	/**
	 * Returns HTTP response code.
	 *
	 * @return int
	 */
	public function getCode()
	{
		return $this->code;
	}


	/**
	 * Sends a HTTP header and replaces a previous one.
	 *
	 * @param  string  header name
	 * @param  string  header value
	 * @return self
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function setHeader($name, $value)
	{
		$this->checkHeaders();
		if ($value === null) {
			header_remove($name);
		} else if (strcasecmp($name, 'Content-Length') === 0 && ini_get('zlib.output_compression')) {
			// ignore, PHP bug #44164
		} else {
			header($name . ': ' . $value, true, $this->code);
		}
		return $this;
	}


	/**
	 * Adds HTTP header.
	 *
	 * @param  string  header name
	 * @param  string  header value
	 * @return self
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function addHeader($name, $value)
	{
		$this->checkHeaders();
		header($name . ': ' . $value, false, $this->code);
		return $this;
	}


	/**
	 * Sends a Content-type HTTP header.
	 *
	 * @param  string  mime-type
	 * @param  string  charset
	 * @return self
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function setContentType($type, $charset = null)
	{
		$this->setHeader('Content-Type', $type . ($charset ? '; charset=' . $charset : ''));
		return $this;
	}


	/**
	 * Redirects to a new URL. Note: call exit() after it.
	 *
	 * @param  string  URL
	 * @param  int     HTTP code
	 * @return void
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function redirect($url, $code = self::S302_FOUND)
	{
		$this->setCode($code);
		$this->setHeader('Location', $url);
		if (preg_match('#^https?:|^\s*+[a-z0-9+.-]*+[^:]#i', $url)) {
			$escapedUrl = htmlSpecialChars($url, ENT_IGNORE | ENT_QUOTES, 'UTF-8');
			echo "<h1>Redirect</h1>\n\n<p><a href=\"$escapedUrl\">Please click here to continue</a>.</p>";
		}
	}


	/**
	 * Sets the number of seconds before a page cached on a browser expires.
	 *
	 * @param  string|int|\DateTime time , value 0 means "until the browser is closed"
	 * @return self
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function setExpiration($time)
	{
		$this->setHeader('Pragma', NULL);
		if (!$time) { // no cache
			$this->setHeader('Cache-Control', 's-maxage=0, max-age=0, must-revalidate');
			$this->setHeader('Expires', 'Mon, 23 Jan 1978 10:00:00 GMT');
			return $this;
		}

		$time = DateTime::from($time);
		$this->setHeader('Cache-Control', 'max-age=' . ($time->format('U') - time()));
		$this->setHeader('Expires', Helpers::formatDate($time));
		return $this;
	}

	public function setOrigin($domain)
	{
		$this->setHeader('Access-Control-Allow-Origin', $domain);
		$this->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
		$this->setHeader('Access-Control-Allow-Headers', 'Cache-Control, Pragma, Origin, Authorization, Content-Type, X-Requested-With');
		$this->setHeader('Access-Control-Allow-Credentials', 'true');
	}

	/**
	 * Checks if headers have been sent.
	 *
	 * @return bool
	 */
	public function isSent()
	{
		return headers_sent();
	}


	/**
	 * Returns value of an HTTP header.
	 *
	 * @param  string
	 * @param  mixed
	 * @return mixed
	 */
	public function getHeader($header, $default = null)
	{
		$header .= ':';
		foreach (headers_list() as $item) {
			if (0 === stripos($item, $header)) {
				return ltrim(substr($item, strlen($header)));
			}
		}
		return $default;
	}


	/**
	 * Returns a list of headers to sent.
	 *
	 * @return array (name => value)
	 */
	public function getHeaders()
	{
		$headers = [];
		foreach (headers_list() as $header) {
			$a = strpos($header, ':');
			$headers[substr($header, 0, $a)] = ltrim(substr($header, $a + 1));
		}
		return $headers;
	}


	/**
	 * @deprecated
	 */
	public static function date($time = null)
	{
		return Helpers::formatDate($time);
	}


	/**
	 * @return void
	 */
	public function __destruct()
	{
		if (self::$fixIE && isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE ') !== false
			&& in_array($this->code, [400, 403, 404, 405, 406, 408, 409, 410, 500, 501, 505], true)
			&& preg_match('#^text/html(?:;|$)#', $this->getHeader('Content-Type', 'text/html'))
		) {
			echo Generator::random(2e3, " \t\r\n"); // sends invisible garbage for IE
			self::$fixIE = false;
		}
	}


	/**
	 * Sends a cookie.
	 *
	 * @param  string name of the cookie
	 * @param  string value
	 * @param  string|int|\DateTime expiration time, value 0 means "until the browser is closed"
	 * @param  string
	 * @param  string
	 * @param  bool
	 * @param  bool
	 * @return self
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function setCookie($name, $value, $time, $path = null, $domain = null, $secure = null, $httpOnly = null)
	{
		$this->checkHeaders();
		setcookie(
			$name,
			$value,
			$time ? (int) DateTime::from($time)->format('U') : 0,
			$path === null ? $this->cookiePath : (string) $path,
			$domain === null ? $this->cookieDomain : (string) $domain,
			$secure === null ? $this->cookieSecure : (bool) $secure,
			$httpOnly === null ? $this->cookieHttpOnly : (bool) $httpOnly
		);
		Helpers::removeDuplicateCookies();
		return $this;
	}

	/**
	 * Deletes a cookie.
	 *
	 * @param  string name of the cookie.
	 * @param  string
	 * @param  string
	 * @param  bool
	 * @return void
	 * @throws \RuntimeException  if HTTP headers have been sent
	 */
	public function deleteCookie($name, $path = null, $domain = null, $secure = null)
	{
		$this->setCookie($name, false, 0, $path, $domain, $secure);
	}

	private function checkHeaders()
	{
		if (PHP_SAPI === 'cli') {
			return;
		} else if (headers_sent($file, $line)) {
			throw new \RuntimeException('Cannot send header after HTTP headers have been sent' . ($file ? " (output started at $file:$line)." : '.'));
		} else if ($this->warnOnBuffer && ob_get_length() && !array_filter(ob_get_status(true), function ($i) {
				return !$i['chunk_size'];
			})
		) {
			trigger_error('Possible problem: you are sending a HTTP header while already having some data in output buffer. Try Hail\Tracy\OutputDebugger or start session earlier.');
		}
	}

}
