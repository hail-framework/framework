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

	public function __construct($url = NULL, string $scriptPath = '')
	{
		parent::__construct($url);
		$this->setScriptPath($scriptPath);
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
