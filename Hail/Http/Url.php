<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;


/**
 * URI Syntax (RFC 3986).
 *
 * <pre>
 * scheme  user  password  host  port  basePath   relativeUrl
 *   |      |      |        |      |    |             |
 * /--\   /--\ /------\ /-------\ /--\/--\/----------------------------\
 * http://john:x0y17575@nette.org:8042/en/manual.php?name=param#fragment  <-- absoluteUrl
 *        \__________________________/\____________/^\________/^\______/
 *                     |                     |           |         |
 *                 authority               path        query    fragment
 * </pre>
 *
 * - authority:   [user[:password]@]host[:port]
 * - hostUrl:     http://user:password@nette.org:8042
 * - basePath:    /en/ (everything before relative URI not including the script name)
 * - baseUrl:     http://user:password@nette.org:8042/en/
 * - relativeUrl: manual.php
 *
 * @property   string $scheme
 * @property   string $user
 * @property   string $password
 * @property   string $host
 * @property   int $port
 * @property   string $path
 * @property   string $query
 * @property   string $fragment
 * @property   string $scriptPath
 */
class Url
{
	/** @var array */
	public static $defaultPorts = array(
		'http' => 80,
		'https' => 443,
		'ftp' => 21,
		'news' => 119,
		'nntp' => 119,
	);

	/** @var array */
	private $url = array(
		'scheme' => '',
		'port' => '',
		'host' => '',
		'user' => '',
		'password' => '',
		'path' => '',
		'fragment' => '',
		'scriptPath' => ''
	);

	protected $query = [];

	/**
	 * @param  string|null $url
	 * @throws \InvalidArgumentException if URL is malformed
	 */
	public function __construct($url = NULL)
	{
		if (!is_string($url)) {
			return;
		}

		$p = parse_url($url);
		if ($p === FALSE) {
			throw new \InvalidArgumentException("Malformed or unsupported URI '$url'.");
		}

		$this->url['scheme'] = isset($p['scheme']) ? $p['scheme'] : '';
		$this->url['port'] = isset($p['port']) ? $p['port'] : (isset(self::$defaultPorts[$p['scheme']]) ? self::$defaultPorts[$p['scheme']] : '');
		$this->url['host'] = isset($p['host']) ? rawurldecode($p['host']) : '';
		$this->url['user'] = isset($p['user']) ? rawurldecode($p['user']) : '';
		$this->url['password'] = isset($p['pass']) ? rawurldecode($p['pass']) : '';
		$this->setPath(isset($p['path']) ? $p['path'] : '');
		$this->setQuery(isset($p['query']) ? $p['query'] : array());
		$this->url['fragment'] = isset($p['fragment']) ? rawurldecode($p['fragment']) : '';
	}

	public function __get($key) {
		return $this->get($key);
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function get($key)
	{
		if (isset($this->url[$key])) {
			return $this->url[$key];
		} elseif ($key === 'query') {
			return http_build_query(
				$this->getQuery(), '', '&', PHP_QUERY_RFC3986
			);
		}

		return null;
	}

	/**
	 * Sets the scheme part of URI.
	 * @param  string
	 * @return self
	 */
	public function setScheme($value)
	{
		$this->url['scheme'] = (string) $value;
		return $this;
	}


	/**
	 * Sets the user name part of URI.
	 * @param  string
	 * @return self
	 */
	public function setUser($value)
	{
		$this->url['user'] = (string) $value;
		return $this;
	}


	/**
	 * Sets the password part of URI.
	 * @param  string
	 * @return self
	 */
	public function setPassword($value)
	{
		$this->url['password'] = (string) $value;
		return $this;
	}

	/**
	 * Sets the host part of URI.
	 * @param  string
	 * @return self
	 */
	public function setHost($value)
	{
		$this->url['host'] = (string) $value;
		$this->setPath($this->url['path']);
		return $this;
	}

	/**
	 * Sets the port part of URI.
	 * @param  int
	 * @return self
	 */
	public function setPort($value)
	{
		$this->url['port'] = (int) $value;
		return $this;
	}

	/**
	 * Sets the path part of URI.
	 * @param  string
	 * @return self
	 */
	public function setPath($value)
	{
		$value = (string) $value;
		if ($this->url['host'] && strncmp($value, '/', 1) !== 0) {
			$value = '/' . $value;
		}
		$this->url['path'] = $value;
		return $this;
	}

	/**
	 * Sets the query part of URI.
	 * @param  string|array
	 * @return self
	 */
	public function setQuery($value)
	{
		$this->query = is_array($value) ? $value : self::parseQuery($value);
		return $this;
	}

	/**
	 * Appends the query part of URI.
	 * @param  string|array
	 * @return self
	 */
	public function appendQuery($value)
	{
		$this->query = is_array($value)
			? $value + $this->query
			: self::parseQuery($this->getQuery() . '&' . $value);
		return $this;
	}

	/**
	 * @param string
	 * @param mixed NULL unsets the parameter
	 * @return self
	 */
	public function setQueryParameter($name, $value)
	{
		$this->query[$name] = $value;
		return $this;
	}

	/**
	 * Sets the fragment part of URI.
	 * @param  string
	 * @return self
	 */
	public function setFragment($value)
	{
		$this->url['fragment'] = (string) $value;
		return $this;
	}

	/**
	 * Sets the script-path part of URI.
	 * @param  string
	 * @return self
	 */
	public function setScriptPath($value)
	{
		$this->url['scriptPath'] = (string) $value;
		return $this;
	}

	/**
	 * Returns the entire URI including query string and fragment.
	 * @return string
	 */
	public function getAbsoluteUrl()
	{
		return $this->getHostUrl() . $this->url['path']
			. (($tmp = $this->get('query')) ? '?' . $tmp : '')
			. ($this->url['fragment'] === '' ? '' : '#' . $this->url['fragment']);
	}


	/**
	 * Returns the [user[:pass]@]host[:port] part of URI.
	 * @return string
	 */
	public function getAuthority()
	{
		return $this->url['host'] === '' ? ''
			: ($this->url['user'] !== '' && $this->url['scheme'] !== 'http' && $this->url['scheme'] !== 'https'
				? rawurlencode($this->url['user']) . ($this->url['password'] === '' ? '' : ':' . rawurlencode($this->url['password'])) . '@'
				: '')
			. $this->url['host']
			. ($this->url['port'] && (!isset(self::$defaultPorts[$this->url['scheme']]) || $this->url['port'] !== self::$defaultPorts[$this->url['scheme']])
				? ':' . $this->url['port']
				: '');
	}


	/**
	 * Returns the scheme and authority part of URI.
	 * @return string
	 */
	public function getHostUrl()
	{
		return ($this->url['scheme'] ? $this->url['scheme'] . ':' : '') . '//' . $this->getAuthority();
	}

	/**
	 * Returns the base-path.
	 * @return string
	 */
	public function getBasePath()
	{
		$pos = strrpos(empty($this->url['scriptPath']) ? $this->url['scriptPath'] : $this->url['path'], '/');
		return $pos === FALSE ? '' : substr($this->url['path'], 0, $pos + 1);
	}

	/**
	 * Returns the additional path information.
	 * @return string
	 */
	public function getPathInfo()
	{
		return (string) substr($this->url['path'], strlen($this->url['scriptPath']));
	}

	/**
	 * Returns the base-URI.
	 * @return string
	 */
	public function getBaseUrl()
	{
		return $this->getHostUrl() . $this->getBasePath();
	}

	/**
	 * Returns variable provided to the script via URL query ($_GET).
	 * If no key is passed, returns the entire array.
	 * @param  string $key key
	 * @return mixed
	 */
	public function getQuery($key = NULL)
	{
		return Helpers::getParams($this->post, '_GET', $key);
	}

	/**
	 * Returns the relative-URI.
	 * @return string
	 */
	public function getRelativeUrl()
	{
		return (string) substr($this->getAbsoluteUrl(), strlen($this->getBaseUrl()));
	}

	/**
	 * Transforms URL to canonical form.
	 * @return self
	 */
	public function canonicalize()
	{
		$this->url['path'] = preg_replace_callback(
			'#[^!$&\'()*+,/:;=@%]+#',
			function($m) { return rawurlencode($m[0]); },
			self::unescape($this->url['path'], '%/')
		);
		$this->url['host'] = strtolower($this->url['host']);
		return $this;
	}


	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->getAbsoluteUrl();
	}


	/**
	 * Similar to rawurldecode, but preserves reserved chars encoded.
	 * @param  string $s to decode
	 * @param  string $reserved reserved characters
	 * @return string
	 */
	public static function unescape($s, $reserved = '%;/?:@&=+$,')
	{
		// reserved (@see RFC 2396) = ";" | "/" | "?" | ":" | "@" | "&" | "=" | "+" | "$" | ","
		// within a path segment, the characters "/", ";", "=", "?" are reserved
		// within a query component, the characters ";", "/", "?", ":", "@", "&", "=", "+", ",", "$" are reserved.
		if ($reserved !== '') {
			$s = preg_replace_callback(
				'#%(' . substr(chunk_split(bin2hex($reserved), 2, '|'), 0, -1) . ')#i',
				function($m) { return '%25' . strtoupper($m[1]); },
				$s
			);
		}
		return rawurldecode($s);
	}


	/**
	 * Parses query string.
	 * @return array
	 */
	public static function parseQuery($s)
	{
		parse_str($s, $res);
		return $res;
	}

}
