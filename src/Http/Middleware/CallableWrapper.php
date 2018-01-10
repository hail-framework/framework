<?php

namespace Hail\Http\Middleware;

use Interop\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};
use Psr\Http\{
    Message\ServerRequestInterface,
    Message\ResponseInterface
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
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws \LogicException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = ($this->handler)($request, $handler);

        if (!($response instanceof ResponseInterface)) {
            throw new \LogicException('The middleware must return a ResponseInterface');
        }

        return $response;
    }
}