<?php

namespace Hail\Http;

use Psr\Container\ContainerInterface;
use Psr\Http\{
	ServerMiddleware\DelegateInterface,
	ServerMiddleware\MiddlewareInterface,
	Message\ResponseInterface,
	Message\ServerRequestInterface
};

/**
 * PSR-7 / PSR-15 middleware dispatcher
 */
class Dispatcher implements MiddlewareInterface
{
	/**
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * @var MiddlewareInterface[]
	 */
	private $middleware;

	/**
	 * @var int
	 */
	private $index;


	/**
	 * @param (callable|MiddlewareInterface|mixed)[] $middleware middleware stack (with at least one middleware component)
	 * @param ContainerInterface|null $container optional middleware resolver:
	 *                                           function (string $name): MiddlewareInterface
	 *
	 * @throws \InvalidArgumentException if an empty middleware stack was given
	 */
	public function __construct(array $middleware, ContainerInterface $container = null)
	{
		if (empty($middleware)) {
			throw new \InvalidArgumentException('Empty middleware queue');
		}

		$this->middleware = $middleware;
		$this->container = $container;
	}

	/**
	 * Return the next available middleware frame in the queue.
	 *
	 * @param ServerRequestInterface $request for matcher
	 *
	 * @return MiddlewareInterface|false
	 */
	public function next(ServerRequestInterface $request): ?MiddlewareInterface
	{
		++$this->index;

		return $this->get($request);
	}

	/**
	 * Dispatch the request, return a response.
	 *
	 * @param ServerRequestInterface $request
	 *
	 * @return ResponseInterface
	 * @throws \LogicException
	 */
	public function dispatch(ServerRequestInterface $request): ResponseInterface
	{
		$this->index = 0;

		return $this->get($request)->process($request, new Delegate($this, null));
	}


	/**
	 * @inheritdoc
	 * @throws \LogicException
	 */
	public function process(ServerRequestInterface $request, DelegateInterface $delegate): ResponseInterface
	{
		$this->index = 0;

		return $this->get($request)->process($request, new Delegate($this, $delegate));
	}

	/**
	 * Return the next available middleware frame in the middleware.
	 *
	 * @param ServerRequestInterface $request for matcher
	 *
	 * @return MiddlewareInterface
	 * @throws \LogicException
	 */
	public function get(ServerRequestInterface $request): ?MiddlewareInterface
	{
		if (!isset($this->middleware[$this->index])) {
			return null;
		}

		$middleware = $this->middleware[$this->index];

		if (is_array($middleware)) {
			$conditions = $middleware;
			$middleware = array_pop($conditions);

			foreach ($conditions as $condition) {
				if ($condition === true) {
					continue;
				}

				if ($condition === false) {
					return $this->next($request);
				}

				if (is_string($condition)) {
					$condition = new Matcher\Path($condition);
				} elseif (!($condition instanceof Matcher\MatcherInterface)) {
					throw new \LogicException('Invalid matcher. Must be a boolean, string or an instance of Hail\\Http\\Matcher\\MatcherInterface');
				}

				if (!$condition->match($request)) {
					return $this->next($request);
				}
			}
		}

		if (is_string($middleware)) {
			if ($this->container === null) {
				throw new \LogicException("No valid middleware provided: $middleware");
			}

			$middleware = $this->container->get($middleware);
		}

		if (is_callable($middleware)) {
			$middleware = new Middleware\CallableWrapper($middleware);
		} elseif (!$middleware instanceof MiddlewareInterface) {
			throw new \LogicException('The middleware must be an instance of MiddlewareInterface');
		}

		return $middleware;
	}
}
