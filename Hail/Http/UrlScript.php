<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modifiend by FlyingHail <flyinghail@msn.com>
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
 * @property   string $scriptPath
 * @property-read string $pathInfo
 */
class UrlScript extends Url
{
	/** @var string */
	private $scriptPath = '/';

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
				$this->setPort(substr($pair[2], 1));
			} elseif (isset($_SERVER['SERVER_PORT'])) {
				$this->setPort($_SERVER['SERVER_PORT']);
			}
		}

		// path & query
		if (isset($_SERVER['REQUEST_URI'])) {
			$path = $_SERVER['REQUEST_URI'];
			if (strpos($path, '?') !== false) {
				$path = strstr($_SERVER['REQUEST_URI'], '?', true);
			}
			if (strpos($path, '//') !== false) {
				$path = preg_replace('#/{2,}#', '/', $path);
			}
		} else {
			$path = '/';
		}
		$path = static::unescape($path, '%/?#');
		$path = htmlspecialchars_decode(
			htmlspecialchars($path, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES
		);
		$this->setPath($path);

		// detect script path
		if ($path !== '/') {
			$lpath = strtolower($path);
			$script = isset($_SERVER['SCRIPT_NAME']) ? strtolower($_SERVER['SCRIPT_NAME']) : '';
			if ($lpath !== $script) {
				$tmp = explode('/', $path);
				$script = explode('/', $script);
				$path = '';
				foreach (explode('/', $lpath) as $k => $v) {
					if ($v !== $script[$k]) {
						break;
					}
					$path .= $tmp[$k] . '/';
				}
			}
			$this->setScriptPath($path);
		}
	}

	/**
	 * Sets the script-path part of URI.
	 * @param  string
	 * @return self
	 */
	public function setScriptPath($value)
	{
		$this->scriptPath = (string) $value;
		return $this;
	}


	/**
	 * Returns the script-path part of URI.
	 * @return string
	 */
	public function getScriptPath()
	{
		return $this->scriptPath;
	}


	/**
	 * Returns the base-path.
	 * @return string
	 */
	public function getBasePath()
	{
		$pos = strrpos($this->scriptPath, '/');
		return $pos === FALSE ? '' : substr($this->getPath(), 0, $pos + 1);
	}

	/**
	 * Returns the additional path information.
	 * @return string
	 */
	public function getPathInfo()
	{
		return (string) substr($this->getPath(), strlen($this->scriptPath));
	}

	/**
	 * Returns the query part of URI.
	 * @return string
	 */
	public function getQuery()
	{
		return http_build_query(
			$this->getQueryParameters(), '', '&', PHP_QUERY_RFC3986
		);
	}

	/**
	 * @return array
	 */
	public function getQueryParameters()
	{
		return Helpers::getParams($this->query, '_GET');
	}

	/**
	 * Returns variable provided to the script via URL query ($_GET).
	 * If no key is passed, returns the entire array.
	 * @param  string $key key
	 * @return mixed
	 */
	public function getQueryParameter($key = NULL)
	{
		return Helpers::getParams($this->query, '_GET', $key);
	}
}
