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

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
	Message\UriInterface,
	Message\RequestInterface,
	Message\ResponseInterface,
	Message\ServerRequestInterface
};

class Cors implements MiddlewareInterface
{
	/** Result */
	/** Request is out of CORS specification */
	private const TYPE_REQUEST_OUT_OF_CORS_SCOPE = 0;

	/** Request is pre-flight */
	private const TYPE_PRE_FLIGHT_REQUEST = 1;

	/** Actual request */
	private const TYPE_ACTUAL_REQUEST = 2;

	/** Request origin is not allowed */
	private const ERROR_ORIGIN_NOT_ALLOWED = 3;

	/** Request method is not supported */
	private const ERROR_METHOD_NOT_SUPPORTED = 4;

	/** Request headers are not supported */
	private const ERROR_HEADERS_NOT_SUPPORTED = 5;

	/** No Host header in request */
	private const ERROR_NO_HOST_HEADER = 6;

	/**
	 * 'All' value for allowed origins.
	 */
	public const VALUE_ALLOW_ORIGIN_ALL = '*';

	/**
	 * 'All' values for allowed headers.
	 *
	 * @deprecated
	 * Please list all supported headers instead. 'All headers allowed' is not supported by browsers.
	 * @see https://github.com/neomerx/cors-psr7/issues/23
	 */
	public const VALUE_ALLOW_ALL_HEADERS = '*';

	/** Settings key */
	public const KEY_SERVER_ORIGIN = 'ORIGIN';
	public const KEY_ALLOWED_ORIGINS = 'ALLOWED_ORIGINS';
	public const KEY_ALLOWED_METHODS = 'ALLOWED_METHODS';
	public const KEY_ALLOWED_HEADERS = 'ALLOWED_HEADERS';
	public const KEY_EXPOSED_HEADERS = 'EXPOSED_HEADERS';
	public const KEY_IS_USING_CREDENTIALS = 'CREDENTIALS';
	public const KEY_FLIGHT_CACHE_MAX_AGE = 'CACHE_MAX_AGE';
	public const KEY_IS_FORCE_ADD_METHODS = 'ADD_METHODS';
	public const KEY_IS_FORCE_ADD_HEADERS = 'ADD_HEADERS';
	public const KEY_IS_CHECK_HOST = 'CHECK_HOST';

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var array
	 */
	private static $simpleMethods = [
		'GET' => true,
		'HEAD' => true,
		'POST' => true,
	];

	/**
	 * @var string[]
	 */
	private static $simpleHeadersExclContentType = [
		'accept',
		'accept-language',
		'content-language',
	];

	/**
	 * Defines the analyzer used.
	 *
	 * @param array $settings
	 */
	public function __construct(array $settings = null)
	{
		$default = self::getDefaultSettings();
		foreach ($default as $k => $v) {
			if (!isset($settings[$k])) {
				$settings[$k] = $v;
			} else {
			    $v = $settings[$k];
			    if ($k === self::KEY_ALLOWED_METHODS) {
                    $settings[$k] = \array_change_key_case($v,  CASE_UPPER);
                } elseif ($k === self::KEY_ALLOWED_ORIGINS || $k === self::KEY_ALLOWED_HEADERS) {
                    $settings[$k] = \array_change_key_case($v,  CASE_LOWER);
                }
            }
		}

		$this->settings = $settings;
	}

	/**
	 * Process a request and return a response.
	 *
	 * @param ServerRequestInterface $request
	 * @param RequestHandlerInterface      $handler
	 *
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		[
			'Request-Type' => $type,
			'Response-Headers' => $headers,
		] = $this->analyze($request);

		switch ($type) {
			case self::ERROR_NO_HOST_HEADER:
			case self::ERROR_ORIGIN_NOT_ALLOWED:
			case self::ERROR_METHOD_NOT_SUPPORTED:
			case self::ERROR_HEADERS_NOT_SUPPORTED:
				return Factory::response(403);

			case self::TYPE_REQUEST_OUT_OF_CORS_SCOPE:
				return $handler->handle($request);

			case self::TYPE_PRE_FLIGHT_REQUEST:
				$response = Factory::response(200);

				return self::withCorsHeaders($response, $headers);

			default:
				$response = $handler->handle($request);

				return self::withCorsHeaders($response, $headers);
		}
	}

	/**
	 * Adds cors headers to the response.
	 *
	 * @param ResponseInterface $response
	 * @param array             $headers
	 *
	 * @return ResponseInterface
	 */
	private static function withCorsHeaders(ResponseInterface $response, array $headers): ResponseInterface
	{
		foreach ($headers as $name => $value) {
			$response = $response->withHeader($name, $value);
		}

		return $response;
	}

	/**
	 * Set request for analysis.
	 *
	 * @param RequestInterface $request
	 *
	 * @return array
	 * @see http://www.w3.org/TR/cors/#resource-processing-model
	 */
	public function analyze(RequestInterface $request): array
	{
		$serverOrigin = Factory::uri($this->settings[self::KEY_SERVER_ORIGIN]);

		// check 'Host' request
		if ($this->settings[self::KEY_IS_CHECK_HOST] && !$this->isSameHost($request, $serverOrigin)) {
			return $this->result(self::ERROR_NO_HOST_HEADER);
		}

		// Request handlers have common part (#6.1.1 - #6.1.2 and #6.2.1 - #6.2.2)

		// #6.1.1 and #6.2.1
		$requestOrigin = $this->getRequestOrigin($request);
		if ($requestOrigin === null || $requestOrigin === $this->getOriginFromUri($serverOrigin)) {
			return $this->result(self::TYPE_REQUEST_OUT_OF_CORS_SCOPE);
		}

		// #6.1.2 and #6.2.2
		if (
			!isset($this->settings[self::KEY_ALLOWED_ORIGINS][self::VALUE_ALLOW_ORIGIN_ALL]) &&
			!isset($this->settings[self::KEY_ALLOWED_ORIGINS][$requestOrigin])
		) {
			return $this->result(self::ERROR_ORIGIN_NOT_ALLOWED);
		}

		// Since this point handlers have their own path for
		// - simple CORS and actual CORS request (#6.1.3 - #6.1.4)
		// - pre-flight request (#6.2.3 - #6.2.10)

		if ($request->getMethod() === 'OPTIONS') {
			return $this->analyzeAsPreFlight($request, $requestOrigin);
		}

		return $this->analyzeAsRequest($requestOrigin);
	}

	/**
	 * Analyze request as simple CORS or/and actual CORS request (#6.1.3 - #6.1.4).
	 *
	 * @param string $requestOrigin
	 *
	 * @return array
	 */
	protected function analyzeAsRequest(string $requestOrigin): array
	{
		$headers = [];

		// #6.1.3
		$headers['Access-Control-Allow-Origin'] = $requestOrigin;
		if ($this->settings[self::KEY_IS_USING_CREDENTIALS]) {
			$headers['Access-Control-Allow-Credentials'] = 'true';
		}
		// #6.4
		$headers['Vary'] = 'Origin';

		// #6.1.4
		$exposedHeaders = $this->getEnabledItems($this->settings[self::KEY_EXPOSED_HEADERS]);
		if (!empty($exposedHeaders)) {
			$headers['Access-Control-Expose-Headers'] = $exposedHeaders;
		}

		return $this->result(self::TYPE_ACTUAL_REQUEST, $headers);
	}

	/**
	 * Analyze request as CORS pre-flight request (#6.2.3 - #6.2.10).
	 *
	 * @param RequestInterface $request
	 * @param string           $requestOrigin
	 *
	 * @return array
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	protected function analyzeAsPreFlight(RequestInterface $request, string $requestOrigin): array
	{
		// #6.2.3
		$requestMethod = $request->getHeaderLine('Access-Control-Request-Method');
		if ($requestMethod === '') {
			return $this->result(self::TYPE_REQUEST_OUT_OF_CORS_SCOPE);
		}

		// OK now we are sure it's a pre-flight request

		// #6.2.4
		/** @var string $requestMethod */
		$requestHeaders = $this->getRequestedHeadersInLowerCase($request);

		// #6.2.5
		if (!isset($this->settings[self::KEY_ALLOWED_METHODS][$requestMethod])) {
			return $this->result(self::ERROR_METHOD_NOT_SUPPORTED);
		}

		// #6.2.6
		if ($this->isRequestAllHeadersSupported($requestHeaders) === false) {
			return $this->result(self::ERROR_HEADERS_NOT_SUPPORTED);
		}

		// pre-flight response headers
		$headers = [];

		// #6.2.7
		$headers['Access-Control-Allow-Origin'] = $requestOrigin;
		if ($this->settings[self::KEY_IS_USING_CREDENTIALS]) {
			$headers['Access-Control-Allow-Credentials'] = 'true';
		}
		// #6.4
		$headers['Vary'] = 'Origin';

		// #6.2.8
		if ($this->settings[self::KEY_FLIGHT_CACHE_MAX_AGE] > 0) {
			$headers['Access-Control-Max-Age'] = (int) $this->settings[self::KEY_FLIGHT_CACHE_MAX_AGE];
		}

		// #6.2.9
		$isSimpleMethod = isset(self::$simpleMethods[$requestMethod]);
		if ($isSimpleMethod === false || $this->settings[self::KEY_IS_FORCE_ADD_METHODS]) {
			/**
			 * @see http://www.w3.org/TR/cors/#resource-preflight-requests
			 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Access_control_CORS#Access-Control-Allow-Methods
			 */
			$headers['Access-Control-Allow-Methods'] = implode(', ',
				$this->getEnabledItems($this->settings[self::KEY_ALLOWED_METHODS])
			);
		}

		// #6.2.10
		// Has only 'simple' headers excluding Content-Type
		$isSimpleExclCT = empty(array_diff($requestHeaders, self::$simpleHeadersExclContentType));
		if ($isSimpleExclCT === false || $this->settings[self::KEY_IS_FORCE_ADD_HEADERS]) {
			$headers['Access-Control-Allow-Headers'] = $this->getRequestAllowedHeaders();
		}

		return $this->result(self::TYPE_PRE_FLIGHT_REQUEST, $headers);
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return string[]
	 */
	protected function getRequestedHeadersInLowerCase(RequestInterface $request): array
	{
		$requestHeaders = $request->getHeaderLine('Access-Control-Request-Headers');
		if ($requestHeaders === '') {
			return [];
		}

		// after explode header names might have spaces in the beginnings and ends...
		$requestHeaders = \explode(',', $requestHeaders);

		// ... so trim the spaces and convert values to lower case
		foreach ($requestHeaders as &$headerName) {
			$headerName = \strtolower(\trim($headerName));
		}

		return $requestHeaders;
	}

	/**
	 * @param RequestInterface $request
	 * @param UriInterface     $serverOrigin
	 *
	 * @return bool
	 */
	protected function isSameHost(RequestInterface $request, UriInterface $serverOrigin): bool
	{
		$hostUrl = $request->getUri();

		return $hostUrl->getPort() === $serverOrigin->getPort() &&
			$hostUrl->getHost() === $serverOrigin->getHost();
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return string
	 */
	protected function getRequestOrigin(RequestInterface $request): string
	{
		$value = $request->getHeaderLine('Origin');
		if ($value) {
			try {
				return $this->getOriginFromUri(Factory::uri($value));
			} catch (\InvalidArgumentException $exception) {
				// return empty
			}
		}

		return '';
	}

	protected function getOriginFromUri(UriInterface $uri): string
	{
		$url = ($scheme = $uri->getScheme()) ? $scheme . ':' : '';
		$url .= ($host = $uri->getHost()) ? '//' . $host : '';
		$url .= ($port = $uri->getPort()) ? ':' . $port : '';

		return $url;
	}

	/**
	 * @param int   $type
	 * @param array $headers
	 *
	 * @return array
	 */
	protected function result($type, array $headers = []): array
	{
		return [
			'Request-Type' => $type,
			'Response-Headers' => $headers,
		];
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
		if (isset($this->settings[self::KEY_ALLOWED_HEADERS][self::VALUE_ALLOW_ALL_HEADERS])) {
			return true;
		}

		foreach ($headers as $header) {
			if (!isset($this->settings[self::KEY_ALLOWED_HEADERS][\strtolower($header)])) {
				return false;
			}
		}

		return true;
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

		return \implode(', ', $enabled);
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
			self::KEY_SERVER_ORIGIN => '',

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

