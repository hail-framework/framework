<?php

declare(strict_types=1);

namespace Hail\Http;

use Psr\Http\Message\UriInterface;

/**
 * PSR-7 URI implementation.
 *
 * @author Michael Dowling
 * @author Tobias Schultze
 * @author Matthew Weier O'Phinney
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Uri implements UriInterface
{
	private static $charUnreserved = 'a-zA-Z0-9_\-\.~';
	private static $charSubDelims = '!\$&\'\(\)\*\+,;=';

	/** @var string Uri scheme. */
	private $scheme = '';

	/** @var string Uri user info. */
	private $userInfo = '';

	/** @var string Uri host. */
	private $host = '';

	/** @var int|null Uri port. */
	private $port;

	/** @var string Uri path. */
	private $path = '';

	/** @var string Uri query string. */
	private $query = '';

	/** @var string Uri fragment. */
	private $fragment = '';

	/**
	 * @param string $uri
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct(string $uri = '')
	{
		if ($uri !== '') {
			$parts = parse_url($uri);
			if ($parts === false) {
				throw new \InvalidArgumentException("Unable to parse URI: $uri");
			}

			$this->applyParts($parts);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function __toString(): string
	{
		return Helpers::createUriString(
			$this->scheme,
			$this->getAuthority(),
			$this->path,
			$this->query,
			$this->fragment
		);
	}

	public function getScheme(): string
	{
		return $this->scheme;
	}

	public function getAuthority(): string
	{
		if ($this->host === '') {
			return '';
		}

		$authority = $this->host;
		if ($this->userInfo !== '') {
			$authority = $this->userInfo . '@' . $authority;
		}

		if ($this->port !== null) {
			$authority .= ':' . $this->port;
		}

		return $authority;
	}

	public function getUserInfo(): string
	{
		return $this->userInfo;
	}

	public function getHost(): string
	{
		return $this->host;
	}

	public function getPort()
	{
		return $this->port;
	}

	public function getPath(): string
	{
		return $this->path;
	}

	public function getQuery(): string
	{
		return $this->query;
	}

	public function getFragment(): string
	{
		return $this->fragment;
	}

	public function withScheme($scheme): self
	{
		$scheme = $this->filterScheme($scheme);

		if ($this->scheme === $scheme) {
			return $this;
		}

		$new = clone $this;
		$new->scheme = $scheme;
		$new->port = $new->filterPort($new->port);

		return $new;
	}

	public function withUserInfo($user, $password = null): self
	{
		$info = $user;
		if ($password !== '') {
			$info .= ':' . $password;
		}

		if ($this->userInfo === $info) {
			return $this;
		}

		$new = clone $this;
		$new->userInfo = $info;

		return $new;
	}

	public function withHost($host): self
	{
		$host = $this->filterHost($host);

		if ($this->host === $host) {
			return $this;
		}

		$new = clone $this;
		$new->host = $host;

		return $new;
	}

	public function withPort($port): self
	{
		$port = $this->filterPort($port);

		if ($this->port === $port) {
			return $this;
		}

		$new = clone $this;
		$new->port = $port;

		return $new;
	}

	public function withPath($path): self
	{
		$path = $this->filterPath($path);

		if ($this->path === $path) {
			return $this;
		}

		$new = clone $this;
		$new->path = $path;

		return $new;
	}

	public function withQuery($query): self
	{
		$query = $this->filterQueryAndFragment($query);

		if ($this->query === $query) {
			return $this;
		}

		$new = clone $this;
		$new->query = $query;

		return $new;
	}

	public function withFragment($fragment): self
	{
		$fragment = $this->filterQueryAndFragment($fragment);

		if ($this->fragment === $fragment) {
			return $this;
		}

		$new = clone $this;
		$new->fragment = $fragment;

		return $new;
	}

	/**
	 * Apply parse_url parts to a URI.
	 *
	 * @param array $parts Array of parse_url parts to apply
	 *
	 * @throws \InvalidArgumentException
	 */
	private function applyParts(array $parts)
	{
		$this->scheme = isset($parts['scheme']) ? $this->filterScheme($parts['scheme']) : '';
		$this->userInfo = $parts['user'] ?? '';
		$this->host = isset($parts['host']) ? $this->filterHost($parts['host']) : '';
		$this->port = isset($parts['port']) ? $this->filterPort($parts['port']) : null;
		$this->path = isset($parts['path']) ? $this->filterPath($parts['path']) : '';
		$this->query = isset($parts['query']) ? $this->filterQueryAndFragment($parts['query']) : '';
		$this->fragment = isset($parts['fragment']) ? $this->filterQueryAndFragment($parts['fragment']) : '';
		if (isset($parts['pass'])) {
			$this->userInfo .= ':' . $parts['pass'];
		}
	}

	/**
	 * @param string $scheme
	 *
	 * @throws \InvalidArgumentException If the scheme is invalid
	 *
	 * @return string
	 */
	private function filterScheme(string $scheme): string
	{
		if (!is_string($scheme)) {
			throw new \InvalidArgumentException('Scheme must be a string');
		}

		return strtolower($scheme);
	}

	/**
	 * @param string $host
	 *
	 * @throws \InvalidArgumentException If the host is invalid
	 *
	 * @return string
	 */
	private function filterHost(string $host): string
	{
		if (!is_string($host)) {
			throw new \InvalidArgumentException('Host must be a string');
		}

		return strtolower($host);
	}

	/**
	 * @param int|null $port
	 *
	 * @throws \InvalidArgumentException If the port is invalid
	 *
	 * @return int|null
	 */
	private function filterPort($port)
	{
		if ($port === null) {
			return null;
		}

		$port = (int) $port;
		if (1 > $port || 0xffff < $port) {
			throw new \InvalidArgumentException(sprintf('Invalid port: %d. Must be between 1 and 65535', $port));
		}

		return Helpers::isNonStandardPort($this->scheme, $port) ? $port : null;
	}

	/**
	 * Filters the path of a URI.
	 *
	 * @param string $path
	 *
	 * @throws \InvalidArgumentException If the path is invalid
	 *
	 * @return string
	 */
	private function filterPath(string $path): string
	{
		return preg_replace_callback(
			'/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
			[$this, 'rawurlencodeMatchZero'],
			$path
		);
	}

	/**
	 * Filters the query string or fragment of a URI.
	 *
	 * @param string $str
	 *
	 * @throws \InvalidArgumentException If the query or fragment is invalid
	 *
	 * @return string
	 */
	private function filterQueryAndFragment(string $str): string
	{
		return preg_replace_callback(
			'/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
			[$this, 'rawurlencodeMatchZero'],
			$str
		);
	}

	private function rawurlencodeMatchZero(array $match): string
	{
		return rawurlencode($match[0]);
	}

	/**
	 * Get a Uri populated with values from server variables.
	 *
	 * @param array $server Typically $_SERVER or similar structure.
	 *
	 * @return UriInterface
	 * @throws \InvalidArgumentException
	 */
	public static function fromArray(array $server)
	{
		$uri = new self('');

		if (isset($server['HTTPS'])) {
			$uri->scheme = $server['HTTPS'] === 'on' ? 'https' : 'http';
		}

		[$host, $port] = Helpers::getHostAndPortFromArray($server);

		$uri->host = $host;
		$uri->port = $uri->filterPort($port);

		$path = Helpers::getRequestUri($server);

		$fragment = '';
		if (strpos($path, '#') !== false) {
			[$path, $fragment] = explode('#', $path, 2);
		}

		$uri->path = $uri->filterPath(explode('?', $path)[0]);
		$uri->fragment = $uri->filterQueryAndFragment($fragment);

		if (isset($server['QUERY_STRING'])) {
			$uri->query = $uri->filterQueryAndFragment(ltrim($server['QUERY_STRING'], '?'));
		}


		return $uri;
	}
}
