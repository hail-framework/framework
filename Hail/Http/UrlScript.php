<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;


/**
 * Extended HTTP URL.
 *
 * <pre>
 * http://nette.org/admin/script.php/pathinfo/?name=param#fragment
 *                 \_______________/\________/
 *                        |              |
 *                   scriptPath       pathInfo
 * </pre>
 *
 * - scriptPath:  /admin/script.php (or simply /admin/ when script is directory index)
 * - pathInfo:    /pathinfo/ (additional path information)
 *
 * @property   string    $scriptPath
 * @property-read string $pathInfo
 */
class UrlScript extends Url
{
	/** @var string */
	private $scriptPath = '';

	/**
	 * DETECTS URI, base path and script path of the request.
	 */
	public function __construct()
	{
		$this->setScheme(!empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https' : 'http');
		$this->setUser(isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '');
		$this->setPassword(isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '');

		// host & port
		if ((isset($_SERVER[$tmp = 'HTTP_HOST']) || isset($_SERVER[$tmp = 'SERVER_NAME']))
			&& preg_match('#^([a-z0-9_.-]+|\[[a-f0-9:]+\])(:\d+)?\z#i', $_SERVER[$tmp], $pair)
		) {
			$this->setHost(strtolower($pair[1]));
			if (isset($pair[2])) {
				$this->setPort((int) substr($pair[2], 1));
			} elseif (isset($_SERVER['SERVER_PORT'])) {
				$this->setPort((int) $_SERVER['SERVER_PORT']);
			}
		}

		// path & query
		$requestUrl = $_SERVER['REQUEST_URI'] ?? '/';
		$path = preg_replace('#^\w++://[^/]++#', '', $requestUrl);
		if (strpos($path, '?') !== false) {
			$path = strstr($path, '?', true);
		}
		$path = static::unescape($path, '%/?#');
		if (strpos($path, '//') !== false) {
			$path = preg_replace('#/{2,}#', '/', $path);
		}
		$path = htmlspecialchars_decode(
			htmlspecialchars($path, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES
		);
		$this->setPath($path);

		// detect script path
		$lpath = strtolower($path);
		$script = isset($_SERVER['SCRIPT_NAME']) ? strtolower($_SERVER['SCRIPT_NAME']) : '';
		if ($lpath !== $script) {
			$max = min(strlen($lpath), strlen($script));
			for ($i = 0; $i < $max && $lpath[$i] === $script[$i]; $i++) {
				;
			}
			$path = $i ? substr($path, 0, strrpos($path, '/', $i - strlen($path) - 1) + 1) : '/';
		}
		$this->setScriptPath($path);
	}

	/**
	 * Sets the script-path part of URI.
	 *
	 * @return static
	 */
	public function setScriptPath(string $value)
	{
		$this->scriptPath = $value;

		return $this;
	}


	/**
	 * Returns the script-path part of URI.
	 */
	public function getScriptPath(): string
	{
		return $this->scriptPath ?: $this->path;
	}


	/**
	 * Returns the base-path.
	 */
	public function getBasePath(): string
	{
		$pos = strrpos($this->getScriptPath(), '/');

		return $pos === false ? '' : substr($this->getPath(), 0, $pos + 1);
	}

	/**
	 * Returns the additional path information.
	 */
	public function getPathInfo(): string
	{
		return (string) substr($this->getPath(), strlen($this->getScriptPath()));
	}

	/**
	 * Returns the query part of URI.
	 */
	public function getQuery(): string
	{
		return http_build_query(
			$this->getQueryParameters(), '', '&', PHP_QUERY_RFC3986
		);
	}

	public function getQueryParameters(): array
	{
		return Helpers::getParam($this->query, '_GET');
	}

	/**
	 * Returns variable provided to the script via URL query ($_GET).
	 * If no key is passed, returns the entire array.
	 *
	 * @return mixed
	 */
	public function getQueryParameter(string $key = null)
	{
		return Helpers::getParam($this->query, '_GET', $key);
	}
}
