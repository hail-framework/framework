<?php

namespace Middlewares;

use Hail\Http\Factory;
use Hail\Http\Middleware\Cors\Settings;
use Hail\Http\Middleware\Cors\Analyzer;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class Cors implements MiddlewareInterface
{
	/**
	 * @var Settings
	 */
	private $settings;

	/**
	 * Defines the analyzer used.
	 *
	 * @param array $settings
	 */
	public function __construct(array $settings = null)
	{
		$this->settings = new Settings($settings);
	}

	/**
	 * Process a request and return a response.
	 *
	 * @param ServerRequestInterface $request
	 * @param DelegateInterface      $delegate
	 *
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, DelegateInterface $delegate)
	{
		[
			'Request-Type' => $type,
			'Response-Headers' => $headers,
		] = (new Analyzer($this->settings))->analyze($request);

		switch ($type) {
			case Analyzer::ERROR_NO_HOST_HEADER:
			case Analyzer::ERROR_ORIGIN_NOT_ALLOWED:
			case Analyzer::ERROR_METHOD_NOT_SUPPORTED:
			case Analyzer::ERROR_HEADERS_NOT_SUPPORTED:
				return Factory::response(403);

			case Analyzer::TYPE_REQUEST_OUT_OF_CORS_SCOPE:
				return $delegate->process($request);

			case Analyzer::TYPE_PRE_FLIGHT_REQUEST:
				$response = Factory::response(200);

				return self::withCorsHeaders($response, $headers);

			default:
				$response = $delegate->process($request);

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
}

