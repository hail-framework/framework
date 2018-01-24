<?php

namespace Hail\Http;

use Psr\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};
use Psr\Container\ContainerInterface;
use Psr\Http\{
    Message\ResponseInterface,
    Message\ServerRequestInterface
};

/**
 * PSR-15 HTTP Server Request Handlers
 */
class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $middleware;

    /**
     * @var int
     */
    private $index = 0;

    /**
     * @param (callable|MiddlewareInterface|mixed)[] $middleware middleware stack (with at least one middleware component)
     * @param ContainerInterface|null $container optional middleware resolver:
     *                                           $container->get(string $name): MiddlewareInterface
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
     * Dispatch the request, return a response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        return $this->get($request)->process($request, $this);
    }


    /**
     * {@inheritdoc}
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->get($request, true);
        if ($middleware === null) {
            throw new \RuntimeException('Middleware queue exhausted, with no response returned.');
        }

        return $middleware->process($request, $this);
    }

    /**
     * Return the current or next available middleware frame in the middleware.
     *
     * @param ServerRequestInterface $request
     * @param bool                   $next
     *
     * @return null|MiddlewareInterface
     * @throws
     */
    protected function get(ServerRequestInterface $request, bool $next = false): ?MiddlewareInterface
    {
        $index = $next ? ++$this->index : $this->index;

        if (!isset($this->middleware[$index])) {
            return null;
        }

        $middleware = $this->middleware[$index];

        if (\is_array($middleware)) {
            $conditions = $middleware;
            $middleware = \array_pop($conditions);

            if (!Matcher\Factory::matches($conditions, $request)) {
                return $this->get($request, true);
            }
        }

        if (\is_callable($middleware)) {
            return new Middleware\CallableWrapper($middleware);
        }

        if (\is_string($middleware)) {
            if ($this->container === null) {
                throw new \RuntimeException("No valid middleware provided: $middleware");
            }

            $middleware = $this->container->get($middleware);
        }

        if (!$middleware instanceof MiddlewareInterface) {
            throw new \RuntimeException('The middleware must be an instance of MiddlewareInterface');
        }

        return $middleware;
    }
}
