<?php

namespace Hail\Http;

use Psr\Http\Message\{
	MessageInterface,
	StreamInterface
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
		return array_map('trim', $values,
			array_fill(0, count($values), " \t")
		);
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
	 * @param array $server  Typically $_SERVER or similar structure.
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
	 * @param array $server  Typically $_SERVER or similar structure.
	 *
	 * @return string
	 */
	public static function getProtocol(array $server): string
	{
		if ($server === null) {
			$server = $_SERVER;
		}

		return isset($server['SERVER_PROTOCOL']) ?
			str_replace('HTTP/', '', $server['SERVER_PROTOCOL']) : '1.1';
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
}