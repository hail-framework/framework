<?php
/**
 * Copyright 2015 info@neomerx.com (www.neomerx.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Hail\Http\Middleware\Cors;

use InvalidArgumentException;

/**
 * @package Neomerx\Cors
 */
class ParsedUrl
{
	/** Default value for port if not specified */
	const DEFAULT_PORT = 80;

	/**
	 * @var string|null
	 */
	private $scheme;

	/**
	 * @var string|null
	 */
	private $host;

	/**
	 * @var int
	 */
	private $port;

	/**
	 * @var null|string
	 */
	private $urlAsString;

	/**
	 * @param string|array $url
	 */
	public function __construct($url)
	{
		if (is_array($url) === true) {
			$parsedUrl = $url;
		} else {
			$parsedUrl = parse_url($url);
		}

		if ($parsedUrl === false) {
			throw new InvalidArgumentException('url');
		}

		$this->scheme = $parsedUrl['scheme'] ?? null;
		$this->host = $parsedUrl['host'] ?? null;
		$this->port = (int) ($parsedUrl['port'] ?? self::DEFAULT_PORT);
	}

	/**
	 * Get URL scheme.
	 *
	 * @return string|null
	 */
	public function getScheme()
	{
		return $this->scheme;
	}

	/**
	 * Get URL host.
	 *
	 * @return string|null
	 */
	public function getHost()
	{
		return $this->host;
	}

	/**
	 * Get URL port.
	 *
	 * @return int|null
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * Get URL string representation.
	 *
	 * @return string
	 */
	public function getOrigin()
	{
		if ($this->urlAsString === null) {
			$url = $this->scheme === null ? '' : $this->scheme . ':';
			$url .= $this->host === null ? '' : '//' . $this->host;
			$url .= $this->port === self::DEFAULT_PORT ? '' : ':' . $this->port;

			$this->urlAsString = $url;
		}

		return $this->urlAsString;
	}

	/**
	 * If schemes are equal.
	 *
	 * @param ParsedUrl $rhs
	 *
	 * @return bool
	 */
	public function isSchemeEqual(ParsedUrl $rhs)
	{
		return strcasecmp($this->getScheme(), $rhs->getScheme()) === 0;
	}

	/**
	 * If hosts are equal.
	 *
	 * @param ParsedUrl $rhs
	 *
	 * @return bool
	 */
	public function isHostEqual(ParsedUrl $rhs)
	{
		return strcasecmp($this->getHost(), $rhs->getHost()) === 0;
	}

	/**
	 * If ports are equal.
	 *
	 * @param ParsedUrl $rhs
	 *
	 * @return bool
	 */
	public function isPortEqual(ParsedUrl $rhs)
	{
		return $this->getPort() === $rhs->getPort();
	}

	public function __toString()
	{
		return $this->getOrigin();
	}
}
