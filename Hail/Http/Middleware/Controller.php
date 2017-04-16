<?php

namespace Hail\Http\Middleware;

use Hail\Container\Container;
use Hail\Dispatcher;
use Hail\Http\Exception\HttpErrorException;
use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class Controller implements MiddlewareInterface
{
	/**
	 * @var Container
	 */
	private $container;

	/**
	 * @param Container $container
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
         * @var Dispatcher $dispatcher
         */
        $dispatcher = $this->container->get('dispatcher');

		if (!$dispatcher->initialized()) {
			return $delegate->process($request);
		}

		[$class, $method] = $this->convert($dispatcher);

		if ($this->container->has($class)) {
			$controller = $this->container->get($class);
		} else {
			$controller = $this->container->create($class, [$request]);
			$this->container->inject($class, $controller);
		}

		$result = $this->container->call([$controller, $method]);

		if ($result instanceof ResponseInterface) {
			return $result;
		}

		return Factory::response(200);
	}

	private function getNamespace(Dispatcher $dispatcher)
	{
		$namespace = 'App\\Controller';

		if ($app = $dispatcher->getApplication()) {
			$namespace .= '\\' . $app;
		}

		return $namespace;
	}

	/**
	 * @param Dispatcher $dispatcher
	 *
	 * @return array
     *
     * @throws HttpErrorException
	 */
	private function convert(Dispatcher $dispatcher)
	{
		$class = $this->class($dispatcher);

		$action = $dispatcher->getAction();
		$actionClass = $class . '\\' . ucfirst($action);

		if (class_exists($actionClass)) {
            $class = $actionClass;
            $method = '__invoke';
		} elseif (class_exists($class)) {
            $method = $action . 'Action';
		}

		if (!isset($method) || !method_exists($class, $method)) {
			throw HttpErrorException::create(404, [
				'controller' => $class,
				'action' => $method ?? $action
			]);
		}

		return [$class, $method];
	}

	/**
	 * @param Dispatcher $dispatcher
	 *
	 * @return string
	 */
	private function class(Dispatcher $dispatcher): string
	{
		$namespace = $this->getNamespace($dispatcher);
		$class = $dispatcher->getController();

		return strpos($class, $namespace) === 0 ? $class : $namespace . '\\' . ucfirst($class);
	}
}