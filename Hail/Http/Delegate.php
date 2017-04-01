<?php

namespace Hail\Http;

use Psr\Http\{
	ServerMiddleware\DelegateInterface,
	Message\ResponseInterface,
	Message\ServerRequestInterface
};

/**
 * PSR-15 delegate wrapper
 *
 */
class Delegate implements DelegateInterface
{
	/**
	 * @var Dispatcher
	 */
	private $dispatcher;

	/**
	 * @var null|DelegateInterface
	 */
	private $delegate;

	/**
	 * @param Dispatcher             $dispatcher
	 * @param DelegateInterface|null $delegate
	 */
	public function __construct(Dispatcher $dispatcher, DelegateInterface $delegate = null)
	{
		$this->dispatcher = $dispatcher;
		$this->delegate = $delegate;
	}

	/**
	 * {@inheritdoc}
	 */
	public function process(ServerRequestInterface $request): ResponseInterface
	{
		$middleware = $this->dispatcher->next($request);
		if ($middleware === null) {
			if ($this->delegate !== null) {
				return $this->delegate->process($request);
			}

			return Factory::response();
		}

		return $middleware->process($request, $this);
	}

	/**
	 * Dispatch the next available middleware and return the response.
	 *
	 * This method duplicates `next()` to provide backwards compatibility with non-PSR 15 middleware.
	 *
	 * @param ServerRequestInterface $request
	 *
	 * @return ResponseInterface
	 */
	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		return $this->process($request);
	}
}
