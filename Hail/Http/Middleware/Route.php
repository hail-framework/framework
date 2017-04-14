<?php

namespace Hail\Http\Middleware;

use Hail\Container\Container;
use Hail\Http\Factory;
use Hail\Router;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class Route implements MiddlewareInterface
{
	/**
	 * @var Container
	 */
	private $container;

	/**
	 * @var Router
	 */
	private $router;

	/**
	 * @param Router    $router
	 * @param Container $container
	 */
	public function __construct(Router $router, Container $container)
	{
		$this->router = $router;
		$this->container = $container;
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
		$result = $this->router->dispatch(
			$request->getMethod(),
			$request->getUri()->getPath()
		);

		if (isset($result['error'])) {
			return Factory::response($result['error']);
		}

		if ($result['handler'] instanceof \Closure) {
			return $this->container->call($result['handler'], [
				$request->withAttribute('params', $result['params']),
				$this->container
			]);
		}

		$result['handler']['params'] = $result['params'];

		return $delegate->process(
			$request->withAttribute('handler', $result['handler'])
		);
	}
}