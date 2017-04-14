<?php

namespace Hail\Http\Middleware;

use Hail\Container\Container;
use Hail\Http\Exception\HttpErrorException;
use Hail\Http\Factory;
use Hail\Http\Request;
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
		$handler = $request->getAttribute('handler');

		if ($handler === null) {
			return $delegate->process($request);
		}

		[$class, $method] = $this->convert($handler);

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

	private function getNamespace(array $handler)
	{
		$namespace = 'App\\Controller';

		if (isset($handler['app'])) {
			$namespace .= '\\' . $handler['app'];
		}

		return $namespace;
	}

	/**
	 * @param array $handler
	 *
	 * @return array
	 */
	private function convert(array $handler)
	{
		$controllerClass = $this->class($handler);

		$action = $handler['action'] ?? 'index';
		$actionClass = $controllerClass . '\\' . ucfirst($action);

		if (class_exists($actionClass)) {
			return [$actionClass, 'indexAction'];
		}

		if (!class_exists($controllerClass)) {
			throw HttpErrorException::create(404, [
				'controller' => $controllerClass
			]);
		}

		$method = lcfirst($action) . 'Action';
		if (!method_exists($controllerClass, $method)) {
			throw HttpErrorException::create(404, [
				'controller' => $controllerClass,
				'action' => $method
			]);
		}

		return [$controllerClass, $method];
	}

	/**
	 * @param array $handler
	 *
	 * @return string
	 */
	private function class(array $handler): string
	{
		$namespace = $this->getNamespace($handler);
		$class = $handler['controller'] ?? 'Index';

		return strpos($class, $namespace) === 0 ? $class : $namespace . '\\' . ucfirst($class);
	}
}