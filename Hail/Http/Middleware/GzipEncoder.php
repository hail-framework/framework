<?php

namespace Hail\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;
use Hail\Http\Factory;
use Hail\Http\Helpers;

class GzipEncoder implements MiddlewareInterface
{
	/**
	 * @var string
	 */
	protected $encoding = 'gzip';

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
		$response = $delegate->process($request);

		if (!$response->hasHeader('Content-Encoding') &&
			stripos($request->getHeaderLine('Accept-Encoding'), $this->encoding) !== false
		) {
			$body = (string) $response->getBody();
			$encoded = $this->encode($body);
			$stream = Factory::stream($encoded);

			$response = $response
				->withHeader('Content-Encoding', $this->encoding)
				->withBody($stream);

			return Helpers::fixContentLength($response);
		}

		return $response;
	}

	/**
	 * Encode the body content.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	protected function encode($content)
	{
		return gzencode($content);
	}
}