<?php

namespace Hail\Http;

use Hail\Util\Strings;
use Psr\Http\Message\{
	MessageInterface, StreamInterface
};

/**
 * Class Helpers
 *
 * @package Hail\Http
 */
class Helpers
{
	private static $schemes = [
		'http' => 80,
		'https' => 443,
	];

	private static $methods = [
		'HEAD' => 'HEAD',
		'GET' => 'GET',
		'POST' => 'POST',
		'PUT' => 'PUT',
		'PATCH' => 'PATCH',
		'DELETE' => 'DELETE',
		'PURGE' => 'PURGE',
		'OPTIONS' => 'OPTIONS',
		'TRACE' => 'TRACE',
		'CONNECT' => 'CONNECT',
	];

	public static function normalizeHeaderName($header)
	{
		if (strpos($header, '_') !== false) {
			$header = str_replace('_', '-', $header);
		}

		return ucwords(strtolower($header), '-');
	}

	public static function getHeaders(array $server = null)
	{
		if ($server === null) {
			if (function_exists('getallheaders')) {
				return getallheaders();
			}

			$server = $_SERVER;
		}

		$headers = [];
		$serverMap = [
			'CONTENT_TYPE' => 'Content-Type',
			'CONTENT_LENGTH' => 'Content-Length',
			'CONTENT_MD5' => 'Content-Md5',
		];

		foreach ($server as $key => $value) {
			if (strpos($key, 'HTTP_') === 0) {
				$key = substr($key, 5);
				if (!isset($serverMap[$key], $server[$key])) {
					$key = self::normalizeHeaderName($key);
					$headers[$key] = $value;
				}
			} elseif (isset($serverMap[$key])) {
				$headers[$serverMap[$key]] = $value;
			}
		}

		if (!isset($headers['Authorization'])) {
			if (isset($server['REDIRECT_HTTP_AUTHORIZATION'])) {
				$headers['Authorization'] = $server['REDIRECT_HTTP_AUTHORIZATION'];
			} elseif (isset($server['PHP_AUTH_USER'])) {
				$headers['Authorization'] = 'Basic ' . base64_encode($server['PHP_AUTH_USER'] . ':' . $server['PHP_AUTH_PW'] ?? '');
			} elseif (isset($server['PHP_AUTH_DIGEST'])) {
				$headers['Authorization'] = $server['PHP_AUTH_DIGEST'];
			}
		}

		return $headers;
	}

	/**
	 * Parse a cookie header according to RFC 6265.
	 *
	 * PHP will replace special characters in cookie names, which results in other cookies not being available due to
	 * overwriting. Thus, the server request should take the cookies from the request header instead.
	 *
	 * @param string $cookieHeader
	 *
	 * @return array
	 */
	public static function parseCookieHeader(string $cookieHeader)
	{
		preg_match_all('(
            (?:^\\n?[ \t]*|;[ ])
            (?P<name>[!#$%&\'*+-.0-9A-Z^_`a-z|~]+)
            =
            (?P<DQUOTE>"?)
                (?P<value>[\x21\x23-\x2b\x2d-\x3a\x3c-\x5b\x5d-\x7e]*)
            (?P=DQUOTE)
            (?=\\n?[ \t]*$|;[ ])
        )x', $cookieHeader, $matches, PREG_SET_ORDER);

		$cookies = [];

		foreach ($matches as $match) {
			$cookies[$match['name']] = urldecode($match['value']);
		}

		return $cookies;
	}

	/**
	 * Trims whitespace from the header values.
	 *
	 * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
	 *
	 * header-field = field-name ":" OWS field-value OWS
	 * OWS          = *( SP / HTAB )
	 *
	 * @param string[] $values Header values
	 *
	 * @return string[] Trimmed header values
	 *
	 * @see https://tools.ietf.org/html/rfc7230#section-3.2.4
	 */
	public static function trimHeaderValues(array $values): array
	{
		foreach ($values as &$v) {
			$v = trim($v, " \t");
		}

		return $values;
	}

	/**
	 * Create a URI string from its various parts.
	 *
	 * @param string $scheme
	 * @param string $authority
	 * @param string $path
	 * @param string $query
	 * @param string $fragment
	 *
	 * @return string
	 */
	public static function createUriString(string $scheme, string $authority, string $path, string $query, string $fragment): string
	{
		$uri = '';

		if ($scheme !== '') {
			$uri .= $scheme . ':';
		}

		if ($authority !== '') {
			$uri .= '//' . $authority;
		}

		if ($path !== '') {
			if ($path[0] !== '/') {
				if ($authority !== '') {
					// If the path is rootless and an authority is present, the path MUST be prefixed by "/"
					$path = '/' . $path;
				}
			} elseif (isset($path[1]) && $path[1] === '/') {
				if ($authority === '') {
					// If the path is starting with more than one "/" and no authority is present, the
					// starting slashes MUST be reduced to one.
					$path = '/' . ltrim($path, '/');
				}
			}

			$uri .= $path;
		}

		if ($query !== '') {
			$uri .= '?' . $query;
		}

		if ($fragment !== '') {
			$uri .= '#' . $fragment;
		}

		return $uri;
	}

	/**
	 * Is a given port non-standard for the current scheme?
	 *
	 * @param string $scheme
	 * @param int    $port
	 *
	 * @return bool
	 */
	public static function isNonStandardPort(string $scheme, int $port): bool
	{
		return !isset(self::$schemes[$scheme]) || $port !== self::$schemes[$scheme];
	}

	/**
	 * Get method from server variables.
	 *
	 * @param array $server Typically $_SERVER or similar structure.
	 *
	 * @return string
	 */
	public static function getMethod(array $server): string
	{
		$method = $server['REQUEST_METHOD'] ?? 'GET';
		if ($method === 'POST' &&
			isset(
				$server['HTTP_X_HTTP_METHOD_OVERRIDE'],
				self::$methods[$server['HTTP_X_HTTP_METHOD_OVERRIDE']]
			)
		) {
			$method = $server['HTTP_X_HTTP_METHOD_OVERRIDE'];
		}

		return $method;
	}

	/**
	 * Get protocol from server variables.
	 *
	 * @param array $server Typically $_SERVER or similar structure.
	 *
	 * @return string
	 */
	public static function getProtocol(array $server): string
	{
		if (!isset($server['SERVER_PROTOCOL'])) {
			return '1.1';
		}

		if (!preg_match('#^(HTTP/)?(?P<version>[1-9]\d*(?:\.\d)?)$#', $server['SERVER_PROTOCOL'], $matches)) {
			throw new \UnexpectedValueException("Unrecognized protocol version ({$server['SERVER_PROTOCOL']})");
		}

		return $matches['version'];
	}

	/**
	 * Copy the contents of a stream into another stream until the given number
	 * of bytes have been read.
	 *
	 * @author Michael Dowling and contributors to guzzlehttp/psr7
	 *
	 * @param StreamInterface $source Stream to read from
	 * @param StreamInterface $dest   Stream to write to
	 * @param int             $maxLen Maximum number of bytes to read. Pass -1
	 *                                to read the entire stream
	 *
	 * @throws \RuntimeException on error
	 */
	public static function copyToStream(StreamInterface $source, StreamInterface $dest, $maxLen = -1)
	{
		if ($maxLen === -1) {
			while (!$source->eof()) {
				if (!$dest->write($source->read(1048576))) {
					break;
				}
			}

			return;
		}

		$bytes = 0;
		while (!$source->eof()) {
			$buf = $source->read($maxLen - $bytes);
			if (!($len = strlen($buf))) {
				break;
			}
			$bytes += $len;
			$dest->write($buf);
			if ($bytes === $maxLen) {
				break;
			}
		}
	}

	/**
	 * Add or remove the Content-Length header
	 * Used by middlewares that modify the body content
	 *
	 * @param MessageInterface $response
	 *
	 * @return MessageInterface
	 */
	public static function fixContentLength(MessageInterface $response): MessageInterface
	{
		$size = $response->getBody()->getSize();
		if ($size !== null) {
			return $response->withHeader('Content-Length', (string) $size);
		}

		return $response->withoutHeader('Content-Length');
	}

	/**
	 * Inject the provided Content-Type, if none is already present.
	 *
	 * @param string $contentType
	 * @param array  $headers
	 *
	 * @return array Headers with injected Content-Type
	 */
	public static function injectContentType(string $contentType, array $headers)
	{
		foreach ($headers as $k => $v) {
			if (strtolower($k) === 'content-type') {
				return $headers;
			}
		}

		$headers['Content-Type'] = [$contentType];

		return $headers;
	}

	/**
	 * Marshal the host and port from HTTP headers and/or the PHP environment
	 *
	 * @param array $server
	 *
	 * @return array
	 */
	public static function getHostAndPortFromArray(array $server): array
	{
		if (isset($server['HTTP_HOST'])) {
			return self::getHostAndPortFromHost($server['HTTP_HOST']);
		}

		if (!isset($server['SERVER_NAME'])) {
			return ['', null];
		}

		$host = $server['SERVER_NAME'];
		$port = null;
		if (isset($server['SERVER_PORT'])) {
			$port = (int) $server['SERVER_PORT'];
		}

		// Misinterpreted IPv6-Address
		// Reported for Safari on Windows
		if (isset($server['SERVER_ADDR']) && preg_match('/^\[[0-9a-fA-F\:]+\]$/', $host)) {
			$host = '[' . $server['SERVER_ADDR'] . ']';
			$port = $port ?: 80;
			if ($port . ']' === substr($host, strrpos($host, ':') + 1)) {
				// The last digit of the IPv6-Address has been taken as port
				// Unset the port so the default port can be used
				$port = null;
			}
		}

		return [$host, $port];
	}

	/**
	 * Marshal the host and port from the request header
	 *
	 * @param string|array $host
	 *
	 * @return array
	 */
	private static function getHostAndPortFromHost($host): array
	{
		if (is_array($host)) {
			$host = implode(', ', $host);
		}

		$port = null;

		// works for regname, IPv4 & IPv6
		if (preg_match('|\:(\d+)$|', $host, $matches)) {
			$host = substr($host, 0, -1 * (strlen($matches[1]) + 1));
			$port = (int) $matches[1];
		}

		return [$host, $port];
	}

	/**
	 * Detect the base URI for the request
	 *
	 * Looks at a variety of criteria in order to attempt to autodetect a base
	 * URI, including rewrite URIs, proxy URIs, etc.
	 *
	 * From ZF2's Zend\Http\PhpEnvironment\Request class
	 *
	 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
	 * @license   http://framework.zend.com/license/new-bsd New BSD License
	 *
	 * @param array $server
	 *
	 * @return string
	 */
	public static function getRequestUri(array $server)
	{
		// IIS7 with URL Rewrite: make sure we get the unencoded url
		// (double slash problem).
		$iisUrlRewritten = $server['IIS_WasUrlRewritten'] ?? null;
		$unencodedUrl = $server['UNENCODED_URL'] ?? null;
		if ('1' === $iisUrlRewritten && !empty($unencodedUrl)) {
			return $unencodedUrl;
		}

		$requestUri = $server['REQUEST_URI'] ?? null;

		// Check this first so IIS will catch.
		$httpXRewriteUrl = $server['HTTP_X_REWRITE_URL'] ?? null;
		if ($httpXRewriteUrl !== null) {
			$requestUri = $httpXRewriteUrl;
		}

		// Check for IIS 7.0 or later with ISAPI_Rewrite
		$httpXOriginalUrl = $server['HTTP_X_ORIGINAL_URL'] ?? null;
		if ($httpXOriginalUrl !== null) {
			$requestUri = $httpXOriginalUrl;
		}

		if ($requestUri !== null) {
			return preg_replace('#^[^/:]+://[^/]+#', '', $requestUri);
		}

		$origPathInfo = $server['ORIG_PATH_INFO'] ?? null;
		if (empty($origPathInfo)) {
			return '/';
		}

		return $origPathInfo;
	}

	/**
	 * @param array $server
	 * @param array $cookies
	 *
	 * @return ServerRequest
	 */
	public static function serverRequestFromArray(array $server, array $cookies = null): ServerRequest
	{
		$method = self::getMethod($server);
		$headers = self::getHeaders($server);

		if (!isset($server['HTTPS'])) {
			$server['HTTPS'] = 'off';
		}

		if (
			$server['HTTPS'] === 'off' &&
			isset($headers['X-Forwarded-Proto']) &&
			$headers['X-Forwarded-Proto'] === 'https'
		) {
			$server['HTTPS'] = 'on';
		}

		$uri = Uri::fromArray($server);

		$protocol = self::getProtocol($server);

		if ($cookies === null && isset($headers['Cookie'])) {
			$cookies = self::parseCookieHeader($headers['Cookie']);
		}

		return new ServerRequest($method, $uri, $headers, null, $protocol, $server, $cookies ?? []);
	}

	/**
	 * Is AJAX request?
	 *
	 * @param MessageInterface $request
	 *
	 * @return bool
	 */
	public static function isAjax(MessageInterface $request): bool
	{
		return $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
	}

	/**
	 * Determine if the request is the result of an PJAX call.
	 *
	 * @param MessageInterface $request
	 *
	 * @return bool
	 */
	public static function isPjax(MessageInterface $request): bool
	{
		return $request->getHeaderLine('X-PJAX') === 'true';
	}

	/**
	 * Determine if the request is sending JSON.
	 *
	 * @param MessageInterface $request
	 *
	 * @return bool
	 */
	public static function isJson(MessageInterface $request): bool
	{
		return Strings::contains(
			$request->getHeaderLine('Content-Type') ?? '', ['/json', '+json']
		);
	}

	/**
	 * Determine if the current request probably expects a JSON response.
	 *
	 * @param MessageInterface $request
	 *
	 * @return bool
	 */
	public static function expectsJson(MessageInterface $request): bool
	{
		return (static::isAjax($request) && !static::isPjax($request)) || static::wantsJson($request);
	}

	/**
	 * Determine if the current request is asking for JSON in return.
	 *
	 * @param MessageInterface $request
	 *
	 * @return bool
	 */
	public static function wantsJson(MessageInterface $request): bool
	{
		$acceptable = $request->getHeaderLine('Accept');

		return $acceptable !== null && Strings::contains($acceptable, ['/json', '+json']);
	}
}