<?php

namespace Hail\Http\Middleware;

use Hail\Container\Container;
use Hail\Http\Factory;
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
     * @param Container  $container
     */
    public function __construct(Container $container)
    {
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
        /**
         * @var \Hail\Router $router
         */
        $router = $this->container->get('router');

        $result = $router->dispatch(
            $request->getMethod(),
            $request->getUri()->getPath()
        );

        if (isset($result['error'])) {
            return Factory::response($result['error']);
        }

        $handler = $result['handler'];
        if ($handler instanceof \Closure) {
            return $this->container->call($result['handler'], [
                $request->withAttribute('params', $result['params']),
                $this->container,
            ]);
        }

        $handler['params'] = $result['params'];

        /**
         * @var \Hail\Dispatcher $dispatcher
         */
        $dispatcher = $this->container->get('dispatcher');
        $dispatcher->current($handler);

		return $delegate->process($request);
	}
}