<?php

namespace Hail\Http\Middleware;

use Psr\Http\{
	Message\ServerRequestInterface,
	Message\ResponseInterface,
	ServerMiddleware\MiddlewareInterface,
	ServerMiddleware\DelegateInterface
};

class CallableWrapper implements MiddlewareInterface
{
	/**
	 * @var callable
	 */
	private $handler;

	/**
	 * Constructor.
	 *
	 * @param callable $handler
	 */
	public function __construct(callable $handler)
	{
		$this->handler = $handler;
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
		return ($this->handler)($request, $delegate);
	}
}