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

//use Psr\Http\Message\RequestInterface;

/**
 * Implements strategy as a simple set of setting identical for all resources and requests.
 *
 * @package Neomerx\Cors
 */
class Settings
{
	/**
	 * 'All' value for allowed origins.
	 */
	const VALUE_ALLOW_ORIGIN_ALL = '*';

	/**
	 * 'All' values for allowed headers.
	 *
	 * @deprecated
	 * Please list all supported headers instead. 'All headers allowed' is not supported by browsers.
	 * @see https://github.com/neomerx/cors-psr7/issues/23
	 */
	const VALUE_ALLOW_ALL_HEADERS = '*';

	/** Settings key */
	const KEY_SERVER_ORIGIN = 0;

	/** Settings key */
	const KEY_SERVER_ORIGIN_SCHEME = 'scheme';

	/** Settings key */
	const KEY_SERVER_ORIGIN_HOST = 'host';

	/** Settings key */
	const KEY_SERVER_ORIGIN_PORT = 'port';

	/** Settings key */
	const KEY_ALLOWED_ORIGINS = 1;

	/** Settings key */
	const KEY_ALLOWED_METHODS = 2;

	/** Settings key */
	const KEY_ALLOWED_HEADERS = 3;

	/** Settings key */
	const KEY_EXPOSED_HEADERS = 4;

	/** Settings key */
	const KEY_IS_USING_CREDENTIALS = 5;

	/** Settings key */
	const KEY_FLIGHT_CACHE_MAX_AGE = 6;

	/** Settings key */
	const KEY_IS_FORCE_ADD_METHODS = 7;

	/** Settings key */
	const KEY_IS_FORCE_ADD_HEADERS = 8;

	/** Settings key */
	const KEY_IS_CHECK_HOST = 9;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @param array $settings
	 */
	public function __construct(array $settings = null)
	{
		$this->settings = $settings ?? self::getDefaultSettings();
	}

	/**
	 * Get all settings in internal format (for caching).
	 *
	 * @return array
	 */
	public function getSettings(): array
	{
		return $this->settings;
	}

	/**
	 * Set settings from data in internal format.
	 *
	 * @param array $settings
	 */
	public function setSettings(array $settings): void
	{
		$this->settings = $settings;
	}

	/**
	 * Get server Origin URL. If array is returned it should be in parse_url() result format.
	 *
	 * @see http://php.net/manual/function.parse-url.php
	 *
	 * @return string|array
	 */
	public function getServerOrigin()
	{
		return $this->settings[self::KEY_SERVER_ORIGIN];
	}

	/**
	 * Set server Origin URL. If array should be in parse_url() result format.
	 *
	 * @see http://php.net/manual/function.parse-url.php
	 *
	 * @param array|string $origin
	 *
	 * @return self
	 */
	public function setServerOrigin($origin): self
	{
		$this->settings[self::KEY_SERVER_ORIGIN] = is_string($origin) === true ? parse_url($origin) : $origin;

		return $this;
	}

	/**
	 * If pre-flight request result should be cached by user agent.
	 *
	 * @return bool
	 */
	public function isPreFlightCanBeCached(): bool
	{
		return $this->getPreFlightCacheMaxAge() > 0;
	}

	/**
	 * Get pre-flight cache max period in seconds.
	 *
	 * @return int
	 */
	public function getPreFlightCacheMaxAge(): int
	{
		return (int) ($this->settings[self::KEY_FLIGHT_CACHE_MAX_AGE] ?? 0);
	}

	/**
	 * Set pre-flight cache max period in seconds.
	 *
	 * @param int $cacheMaxAge
	 *
	 * @return self
	 */
	public function setPreFlightCacheMaxAge(int $cacheMaxAge): self
	{
		$this->settings[self::KEY_FLIGHT_CACHE_MAX_AGE] = $cacheMaxAge;

		return $this;
	}

	/**
	 * If allowed methods should be added to pre-flight response when 'simple' method is requested (see #6.2.9 CORS).
	 *
	 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
	 *
	 * @return bool
	 */
	public function isForceAddAllowedMethodsToPreFlightResponse(): bool
	{
		return (bool) ($this->settings[self::KEY_IS_FORCE_ADD_METHODS] ?? false);
	}

	/**
	 * If allowed methods should be added to pre-flight response when 'simple' method is requested (see #6.2.9 CORS).
	 *
	 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
	 *
	 * @param bool $forceFlag
	 *
	 * @return self
	 */
	public function setForceAddAllowedMethodsToPreFlightResponse(bool $forceFlag): self
	{
		$this->settings[self::KEY_IS_FORCE_ADD_METHODS] = $forceFlag;

		return $this;
	}

	/**
	 * If allowed headers should be added when request headers are 'simple' and
	 * non of them is 'Content-Type' (see #6.2.10 CORS).
	 *
	 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
	 *
	 * @return bool
	 */
	public function isForceAddAllowedHeadersToPreFlightResponse(): bool
	{
		return (bool) ($this->settings[self::KEY_IS_FORCE_ADD_HEADERS] ?? false);
	}

	/**
	 * If allowed headers should be added when request headers are 'simple' and
	 * non of them is 'Content-Type' (see #6.2.10 CORS).
	 *
	 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
	 *
	 * @param bool $forceFlag
	 *
	 * @return self
	 */
	public function setForceAddAllowedHeadersToPreFlightResponse(bool $forceFlag): self
	{
		$this->settings[self::KEY_IS_FORCE_ADD_HEADERS] = $forceFlag;

		return $this;
	}

	/**
	 * If access with credentials is supported by the resource.
	 *
	 * @return bool
	 */
	public function isRequestCredentialsSupported(): bool
	{
		return (bool) ($this->settings[self::KEY_IS_USING_CREDENTIALS] ?? false);
	}

	/**
	 * If access with credentials is supported by the resource.
	 *
	 * @param bool $isSupported
	 *
	 * @return self
	 */
	public function setRequestCredentialsSupported(bool $isSupported): self
	{
		$this->settings[self::KEY_IS_USING_CREDENTIALS] = $isSupported;

		return $this;
	}

	/**
	 * If request origin is allowed.
	 *
	 * @param ParsedUrl $requestOrigin
	 *
	 * @return bool
	 */
	public function isRequestOriginAllowed(ParsedUrl $requestOrigin): bool
	{
		// check if all origins are allowed with '*'
		$isAllowed =
			isset($this->settings[self::KEY_ALLOWED_ORIGINS]['*']);

		if ($isAllowed === false) {
			$requestOriginStr = strtolower($requestOrigin->getOrigin());
			$isAllowed = isset($this->settings[self::KEY_ALLOWED_ORIGINS][$requestOriginStr]);
		}

		return $isAllowed;
	}

	/**
	 * Set allowed origins. Should be a list of origins (lower-cased, no trail slashes) as keys and null/true as values.
	 *
	 * @param array $origins
	 *
	 * @return self
	 */
	public function setRequestAllowedOrigins(array $origins): self
	{
		$this->settings[self::KEY_ALLOWED_ORIGINS] = [];
		foreach ($origins as $origin => $enabled) {
			$lcOrigin = strtolower($origin);
			$this->settings[self::KEY_ALLOWED_ORIGINS][$lcOrigin] = $enabled;
		}

		return $this;
	}

	/**
	 * If method is supported for actual request (case-sensitive compare).
	 *
	 * @param string $method
	 *
	 * @return bool
	 */
	public function isRequestMethodSupported($method): bool
	{
		return isset($this->settings[self::KEY_ALLOWED_METHODS][$method]);
	}

	/**
	 * If requests headers are allowed (case-insensitive compare).
	 *
	 * @param string[] $headers
	 *
	 * @return bool
	 */
	public function isRequestAllHeadersSupported(array $headers): bool
	{
		$allSupported = true;

		if (isset($this->settings[self::KEY_ALLOWED_HEADERS][self::VALUE_ALLOW_ALL_HEADERS]) === true) {
			return $allSupported;
		}

		foreach ($headers as $header) {
			$lcHeader = strtolower($header);
			if (isset($this->settings[self::KEY_ALLOWED_HEADERS][$lcHeader]) === false) {
				$allSupported = false;
				break;
			}
		}

		return $allSupported;
	}

	/**
	 * Get methods allowed for request. May return originally requested method ($requestMethod) or
	 * comma separated method list (#6.2.9 CORS).
	 *
	 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS#Access-Control-Allow-Methods
	 *
	 * @return string
	 */
	public function getRequestAllowedMethods(): string
	{
		return implode(', ', $this->getEnabledItems($this->settings[self::KEY_ALLOWED_METHODS]));
	}

	/**
	 * Set allowed methods. Should be a list of methods (case sensitive) as keys and null/true as values.
	 *
	 * @param array $methods
	 *
	 * @return self
	 */
	public function setRequestAllowedMethods(array $methods): self
	{
		$this->settings[self::KEY_ALLOWED_METHODS] = [];
		foreach ($methods as $method => $enabled) {
			$this->settings[self::KEY_ALLOWED_METHODS][$method] = $enabled;
		}

		return $this;
	}

	/**
	 * Get headers allowed for request (comma-separated list).
	 *
	 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
	 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS#Access-Control-Allow-Headers
	 *
	 * @return string
	 */
	public function getRequestAllowedHeaders(): string
	{
		$headers = $this->settings[self::KEY_ALLOWED_HEADERS];

		// 'all headers' is not a header actually so we remove it
		unset($headers[self::VALUE_ALLOW_ALL_HEADERS]);

		$enabled = $this->getEnabledItems($headers);

		return implode(', ', $enabled);
	}

	/**
	 * Set allowed headers. Should be a list of headers (case insensitive) as keys and null/true as values.
	 *
	 * @param array $headers
	 *
	 * @return self
	 */
	public function setRequestAllowedHeaders(array $headers): self
	{
		$this->settings[self::KEY_ALLOWED_HEADERS] = [];
		foreach ($headers as $header => $enabled) {
			$lcHeader = strtolower($header);
			$this->settings[self::KEY_ALLOWED_HEADERS][$lcHeader] = $enabled;
		}

		return $this;
	}

	/**
	 * Get headers other than the simple ones that might be exposed to user agent.
	 *
	 * @return string[]
	 */
	public function getResponseExposedHeaders(): array
	{
		return $this->getEnabledItems($this->settings[self::KEY_EXPOSED_HEADERS]);
	}

	/**
	 * Set headers other than the simple ones that might be exposed to user agent.
	 * Should be a list of headers (case insensitive) as keys and null/true as values.
	 *
	 * @param array $headers
	 *
	 * @return self
	 */
	public function setResponseExposedHeaders(array $headers): self
	{
		$this->settings[self::KEY_EXPOSED_HEADERS] = $headers;

		return $this;
	}

	/**
	 * If request 'Host' header should be checked against server's origin.
	 * Check of Host header is strongly encouraged by #6.3 CORS.
	 * Header 'Host' must present for all requests rfc2616 14.23
	 *
	 * @return bool
	 */
	public function isCheckHost(): bool
	{
		return (bool) ($this->settings[self::KEY_IS_CHECK_HOST] ?? false);
	}

	/**
	 * If request 'Host' header should be checked against server's origin.
	 * Check of Host header is strongly encouraged by #6.3 CORS.
	 * Header 'Host' must present for all requests rfc2616 14.23
	 *
	 * @param bool $checkFlag
	 *
	 * @return self
	 */
	public function setCheckHost(bool $checkFlag): self
	{
		$this->settings[self::KEY_IS_CHECK_HOST] = $checkFlag;

		return $this;
	}

	/**
	 * Select only enabled items from $list.
	 *
	 * @param array $list
	 *
	 * @return array
	 */
	protected function getEnabledItems(array $list): array
	{
		$items = [];

		foreach ($list as $item => $enabled) {
			if ($enabled === true) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * @return array
	 */
	protected static function getDefaultSettings(): array
	{
		return [
			/**
			 * Array should be in parse_url() result format.
			 *
			 * @see http://php.net/manual/function.parse-url.php
			 */
			self::KEY_SERVER_ORIGIN => [
				self::KEY_SERVER_ORIGIN_SCHEME => '',
				self::KEY_SERVER_ORIGIN_HOST => '',
				self::KEY_SERVER_ORIGIN_PORT => ParsedUrl::DEFAULT_PORT,
			],
			/**
			 * A list of allowed request origins (lower-cased, no trail slashes).
			 * Value `true` enables and value `null` disables origin.
			 * If all origins '*' are enabled all settings for other origins are ignored.
			 * For example, [
			 *     'http://example.com:123'     => true,
			 *     'http://evil.com'            => null,
			 *     self::VALUE_ALLOW_ORIGIN_ALL => null,
			 * ];
			 */
			self::KEY_ALLOWED_ORIGINS => [],
			/**
			 * A list of allowed request methods (case sensitive).
			 * Value `true` enables and value `null` disables method.
			 * For example, [
			 *     'GET'    => true,
			 *     'PATCH'  => true,
			 *     'POST'   => true,
			 *     'PUT'    => null,
			 *     'DELETE' => true,
			 * ];
			 * Security Note: you have to remember CORS is not access control system and you should not expect all
			 * cross-origin requests will have pre-flights. For so-called 'simple' methods with so-called 'simple'
			 * headers request will be made without pre-flight. Thus you can not restrict such requests with CORS
			 * and should use other means.
			 * For example method 'GET' without any headers or with only 'simple' headers will not have pre-flight
			 * request so disabling it will not restrict access to resource(s).
			 * You can read more on 'simple' methods at http://www.w3.org/TR/cors/#simple-method
			 */
			self::KEY_ALLOWED_METHODS => [],
			/**
			 * A list of allowed request headers (lower-cased). Value `true` enables and
			 * value `null` disables header.
			 * For example, [
			 *     'content-type'                => true,
			 *     'x-custom-request-header'     => null,
			 *     self::VALUE_ALLOW_ALL_HEADERS => null,
			 * ];
			 * Security Note: you have to remember CORS is not access control system and you should not expect all
			 * cross-origin requests will have pre-flights. For so-called 'simple' methods with so-called 'simple'
			 * headers request will be made without pre-flight. Thus you can not restrict such requests with CORS
			 * and should use other means.
			 * For example method 'GET' without any headers or with only 'simple' headers will not have pre-flight
			 * request so disabling it will not restrict access to resource(s).
			 * You can read more on 'simple' headers at http://www.w3.org/TR/cors/#simple-header
			 */
			self::KEY_ALLOWED_HEADERS => [],
			/**
			 * A list of headers (case insensitive) which will be made accessible to
			 * user agent (browser) in response.
			 * Value `true` enables and value `null` disables header.
			 * For example, [
			 *     'Content-Type'             => true,
			 *     'X-Custom-Response-Header' => true,
			 *     'X-Disabled-Header'        => null,
			 * ];
			 */
			self::KEY_EXPOSED_HEADERS => [],
			/**
			 * If access with credentials is supported by the resource.
			 */
			self::KEY_IS_USING_CREDENTIALS => false,
			/**
			 * Pre-flight response cache max period in seconds.
			 */
			self::KEY_FLIGHT_CACHE_MAX_AGE => 0,
			/**
			 * If allowed methods should be added to pre-flight response when
			 * 'simple' method is requested (see #6.2.9 CORS).
			 *
			 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
			 */
			self::KEY_IS_FORCE_ADD_METHODS => false,
			/**
			 * If allowed headers should be added when request headers are 'simple' and
			 * non of them is 'Content-Type' (see #6.2.10 CORS).
			 *
			 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
			 */
			self::KEY_IS_FORCE_ADD_HEADERS => false,
			/**
			 * If request 'Host' header should be checked against server's origin.
			 */
			self::KEY_IS_CHECK_HOST => false,
		];
	}
}
