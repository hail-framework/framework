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
use Psr\Http\Message\RequestInterface;

/**
 * @package Neomerx\Cors
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Analyzer
{
	/* Result */
	/** Request is out of CORS specification */
	const TYPE_REQUEST_OUT_OF_CORS_SCOPE = 0;

	/** Request is pre-flight */
	const TYPE_PRE_FLIGHT_REQUEST = 1;

	/** Actual request */
	const TYPE_ACTUAL_REQUEST = 2;

	/** Request origin is not allowed */
	const ERROR_ORIGIN_NOT_ALLOWED = 3;

	/** Request method is not supported */
	const ERROR_METHOD_NOT_SUPPORTED = 4;

	/** Request headers are not supported */
	const ERROR_HEADERS_NOT_SUPPORTED = 5;

	/** No Host header in request */
	const ERROR_NO_HOST_HEADER = 6;


	/** HTTP method for pre-flight request */
	const PRE_FLIGHT_METHOD = 'OPTIONS';

	/**
	 * @var array
	 */
	private $simpleMethods = [
		'GET' => true,
		'HEAD' => true,
		'POST' => true,
	];

	/**
	 * @var string[]
	 */
	private $simpleHeadersExclContentType = [
		'accept',
		'accept-language',
		'content-language',
	];

	/**
	 * @var Settings
	 */
	private $strategy;

	/**
	 * @param Settings $strategy
	 */
	public function __construct(Settings $strategy)
	{
		$this->strategy = $strategy;
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
		return $this->analyzeImplementation($request);
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return array
	 */
	protected function analyzeImplementation(RequestInterface $request): array
	{
		$serverOrigin = new ParsedUrl($this->strategy->getServerOrigin());

		// check 'Host' request
		if ($this->strategy->isCheckHost() === true && $this->isSameHost($request, $serverOrigin) === false) {
			return $this->createResult(self::ERROR_NO_HOST_HEADER);
		}

		// Request handlers have common part (#6.1.1 - #6.1.2 and #6.2.1 - #6.2.2)

		// #6.1.1 and #6.2.1
		$requestOrigin = $this->getOrigin($request);
		if ($requestOrigin === null || $this->isCrossOrigin($requestOrigin, $serverOrigin) === false) {
			return $this->createResult(self::TYPE_REQUEST_OUT_OF_CORS_SCOPE);
		}

		// #6.1.2 and #6.2.2
		if ($this->strategy->isRequestOriginAllowed($requestOrigin) === false) {

			return $this->createResult(self::ERROR_ORIGIN_NOT_ALLOWED);
		}

		// Since this point handlers have their own path for
		// - simple CORS and actual CORS request (#6.1.3 - #6.1.4)
		// - pre-flight request (#6.2.3 - #6.2.10)

		if ($request->getMethod() === self::PRE_FLIGHT_METHOD) {
			$result = $this->analyzeAsPreFlight($request, $requestOrigin);
		} else {
			$result = $this->analyzeAsRequest($requestOrigin);
		}

		return $result;
	}

	/**
	 * Analyze request as simple CORS or/and actual CORS request (#6.1.3 - #6.1.4).
	 *
	 * @param ParsedUrl        $requestOrigin
	 *
	 * @return array
	 */
	protected function analyzeAsRequest(ParsedUrl $requestOrigin): array
	{
		$headers = [];

		// #6.1.3
		$headers['Access-Control-Allow-Origin'] = $requestOrigin->getOrigin();
		if ($this->strategy->isRequestCredentialsSupported() === true) {
			$headers['Access-Control-Allow-Credentials'] = 'true';
		}
		// #6.4
		$headers['Vary'] = 'Origin';

		// #6.1.4
		$exposedHeaders = $this->strategy->getResponseExposedHeaders();
		if (empty($exposedHeaders) === false) {
			$headers['Access-Control-Expose-Headers'] = $exposedHeaders;
		}

		return $this->createResult(self::TYPE_ACTUAL_REQUEST, $headers);
	}

	/**
	 * Analyze request as CORS pre-flight request (#6.2.3 - #6.2.10).
	 *
	 * @param RequestInterface $request
	 * @param ParsedUrl        $requestOrigin
	 *
	 * @return array
	 *
	 * @SuppressWarnings(PHPMD.NPathComplexity)
	 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
	 */
	protected function analyzeAsPreFlight(RequestInterface $request, ParsedUrl $requestOrigin): array
	{
		// #6.2.3
		$requestMethod = $request->getHeader('Access-Control-Request-Method');
		if (empty($requestMethod) === true) {
			return $this->createResult(self::TYPE_REQUEST_OUT_OF_CORS_SCOPE);
		}

		$requestMethod = $requestMethod[0];

		// OK now we are sure it's a pre-flight request

		/** @var string $requestMethod */

		// #6.2.4
		$requestHeaders = $this->getRequestedHeadersInLowerCase($request);

		// #6.2.5
		if ($this->strategy->isRequestMethodSupported($requestMethod) === false) {
			return $this->createResult(self::ERROR_METHOD_NOT_SUPPORTED);
		}

		// #6.2.6
		if ($this->strategy->isRequestAllHeadersSupported($requestHeaders) === false) {
			return $this->createResult(self::ERROR_HEADERS_NOT_SUPPORTED);
		}

		// pre-flight response headers
		$headers = [];

		// #6.2.7
		$headers['Access-Control-Allow-Origin'] = $requestOrigin->getOrigin();
		if ($this->strategy->isRequestCredentialsSupported() === true) {
			$headers['Access-Control-Allow-Credentials'] = 'true';
		}
		// #6.4
		$headers['Vary'] = 'Origin';

		// #6.2.8
		if ($this->strategy->isPreFlightCanBeCached() === true) {
			$headers['Access-Control-Max-Age'] = $this->strategy->getPreFlightCacheMaxAge();
		}

		// #6.2.9
		$isSimpleMethod = isset($this->simpleMethods[$requestMethod]);
		if ($isSimpleMethod === false || $this->strategy->isForceAddAllowedMethodsToPreFlightResponse() === true) {
			$headers['Access-Control-Allow-Methods'] = $this->strategy->getRequestAllowedMethods();
		}

		// #6.2.10
		// Has only 'simple' headers excluding Content-Type
		$isSimpleExclCT = empty(array_diff($requestHeaders, $this->simpleHeadersExclContentType));
		if ($isSimpleExclCT === false || $this->strategy->isForceAddAllowedHeadersToPreFlightResponse() === true) {
			$headers['Access-Control-Allow-Headers'] = $this->strategy->getRequestAllowedHeaders();
		}

		return $this->createResult(self::TYPE_PRE_FLIGHT_REQUEST, $headers);
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return string[]
	 */
	protected function getRequestedHeadersInLowerCase(RequestInterface $request): array
	{
		$requestHeaders = $request->getHeader('Access-Control-Request-Headers');
		if (empty($requestHeaders) === false) {
			// after explode header names might have spaces in the beginnings and ends...
			$requestHeaders = explode(',', $requestHeaders[0]);
			// ... so trim the spaces and convert values to lower case
			$requestHeaders = array_map(function ($headerName) {
				return strtolower(trim($headerName));
			}, $requestHeaders);
		}

		return $requestHeaders;
	}

	/**
	 * @param RequestInterface $request
	 * @param ParsedUrl        $serverOrigin
	 *
	 * @return bool
	 */
	protected function isSameHost(RequestInterface $request, ParsedUrl $serverOrigin): bool
	{
		$host = $this->getRequestHostHeader($request);
		$hostUrl = $host === null ? null : new ParsedUrl($host);

		$isSameHost =
			$hostUrl !== null &&
			$serverOrigin->isPortEqual($hostUrl) === true &&
			$serverOrigin->isHostEqual($hostUrl) === true;

		return $isSameHost;
	}

	/**
	 * @param ParsedUrl $requestOrigin
	 * @param ParsedUrl $serverOrigin
	 *
	 * @return bool
	 *
	 * @see http://tools.ietf.org/html/rfc6454#section-5
	 */
	protected function isSameOrigin(ParsedUrl $requestOrigin, ParsedUrl $serverOrigin): bool
	{
		return
			$requestOrigin->isHostEqual($serverOrigin) === true &&
			$requestOrigin->isPortEqual($serverOrigin) === true &&
			$requestOrigin->isSchemeEqual($serverOrigin) === true;
	}

	/**
	 * @param ParsedUrl $requestOrigin
	 * @param ParsedUrl $serverOrigin
	 *
	 * @return bool
	 */
	protected function isCrossOrigin(ParsedUrl $requestOrigin, ParsedUrl $serverOrigin): bool
	{
		return $this->isSameOrigin($requestOrigin, $serverOrigin) === false;
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return ParsedUrl|null
	 */
	protected function getOrigin(RequestInterface $request): ?ParsedUrl
	{
		$origin = null;
		if ($request->hasHeader('Origin') === true) {
			$header = $request->getHeader('Origin');
			if (empty($header) === false) {
				$value = $header[0];
				try {
					$origin = new ParsedUrl($value);
				} catch (InvalidArgumentException $exception) {

				}
			}
		}

		return $origin;
	}

	/**
	 * @param int   $type
	 * @param array $headers
	 *
	 * @return array
	 */
	protected function createResult($type, array $headers = []): array
	{
		return [
			'Request-Type' => $type,
			'Response-Headers' => $headers
		];
	}

	/**
	 * @param RequestInterface $request
	 *
	 * @return null|string
	 */
	private function getRequestHostHeader(RequestInterface $request)
	{
		$hostHeaderValue = $request->getHeader('Host');

		return empty($hostHeaderValue) === true ? null : $hostHeaderValue[0];
	}
}
