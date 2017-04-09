<?php

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\StreamInterface;
use Exception;


class UrlEncodePayload implements MiddlewareInterface
{
	/**
	 * @var string
	 */
	protected $contentType = 'application/x-www-form-urlencoded';

	/**
	 * @var bool
	 */
	protected $override = false;

	/**
	 * @var string[]
	 */
	protected $methods = ['POST', 'PUT', 'PATCH', 'DELETE', 'COPY', 'LOCK', 'UNLOCK'];

	/**
	 * Configure the Content-Type.
	 *
	 * @param string $contentType
	 *
	 * @return self
	 */
	public function contentType(string $contentType)
	{
		$this->contentType = $contentType;

		return $this;
	}

	/**
	 * Configure the methods allowed.
	 *
	 * @param string[] $methods
	 *
	 * @return self
	 */
	public function methods(array $methods)
	{
		$this->methods = $methods;

		return $this;
	}

	/**
	 * Configure if the parsed body overrides the previous value.
	 *
	 * @param bool $override
	 *
	 * @return self
	 */
	public function override(bool $override = true)
	{
		$this->override = $override;

		return $this;
	}

	/**
	 * Process a server request and return a response.
	 *
	 * @param ServerRequestInterface $request
	 * @param DelegateInterface      $delegate
	 *
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, DelegateInterface $delegate)
	{
		if ((!$request->getParsedBody() || $this->override)
			&& in_array($request->getMethod(), $this->methods, true)
			&& stripos($request->getHeaderLine('Content-Type'), $this->contentType) === 0
		) {
			try {
				$request = $request->withParsedBody($this->parse($request->getBody()));
			} catch (Exception $exception) {
				return Factory::response(400);
			}
		}

		return $delegate->process($request);
	}

	/**
	 * Parse the body.
	 *
	 * @param StreamInterface $stream
	 *
	 * @return array
	 */
	protected function parse(StreamInterface $stream)
	{
		parse_str((string) $stream, $data);

		return $data ?: [];
	}
}