<?php

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class MethodOverride implements MiddlewareInterface
{
	const HEADER = 'X-Http-Method-Override';

	/**
	 * @var array Allowed methods overrided in GET
	 */
	private $get = ['HEAD', 'CONNECT', 'TRACE', 'OPTIONS'];

	/**
	 * @var array Allowed methods overrided in POST
	 */
	private $post = ['PATCH', 'PUT', 'DELETE', 'COPY', 'LOCK', 'UNLOCK'];

	/**
	 * @var null|string The POST parameter name
	 */
	private $parsedBodyParameter;
	/**
	 * @var null|string The GET parameter name
	 */
	private $queryParameter;

	/**
	 * Set allowed method for GET.
	 *
	 * @param array $methods
	 *
	 * @return self
	 */
	public function get(array $methods)
	{
		$this->get = $methods;

		return $this;
	}

	/**
	 * Set allowed method for POST.
	 *
	 * @param array $methods
	 *
	 * @return self
	 */
	public function post(array $methods)
	{
		$this->post = $methods;

		return $this;
	}

	/**
	 * Configure the parameter using in GET requests.
	 *
	 * @param string $name
	 *
	 * @return self
	 */
	public function queryParameter($name)
	{
		$this->queryParameter = $name;

		return $this;
	}

	/**
	 * Configure the parameter using in POST requests.
	 *
	 * @param string $name
	 *
	 * @return self
	 */
	public function parsedBodyParameter($name)
	{
		$this->parsedBodyParameter = $name;

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
		$requestMethod = $request->getMethod();
		$method = $this->getOverrideMethod($requestMethod, $request);

		if (!empty($method) && $method !== $requestMethod) {
			$allowed = $this->getAllowedOverrideMethods($requestMethod);
			if ([] !== $allowed) {
				if (in_array($method, $allowed, true)) {
					$request = $request->withMethod($method);
				} else {
					return Factory::response(405);
				}
			}
		}

		return $delegate->process($request);
	}

	/**
	 * Returns the override method.
	 *
	 * @param string                 $requestMethod
	 * @param ServerRequestInterface $request
	 *
	 * @return string
	 */
	private function getOverrideMethod(string $requestMethod, ServerRequestInterface $request)
	{
		switch ($requestMethod) {
			case 'POST':
				if ($this->parsedBodyParameter !== null) {
					$method = $request->getParsedBody()[$this->parsedBodyParameter] ?? null;
				}
				break;

			case 'GET':
				if ($this->queryParameter !== null) {
					$method = $request->getQueryParams()[$this->queryParameter] ?? null;
				}
				break;
		}

		if (!isset($method)) {
			$method = $request->getHeaderLine(self::HEADER);
		}

		return strtoupper($method);
	}

	/**
	 * Returns the allowed override methods.
	 *
	 * @param string $method
	 *
	 * @return array
	 */
	private function getAllowedOverrideMethods(string $method): array
	{
		switch ($method) {
			case 'GET':
				return $this->get;
			case 'POST':
				return $this->post;
			default:
				return [];
		}
	}
}