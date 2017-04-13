<?php

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class BasePath implements MiddlewareInterface
{
	/**
	 * @var string The path prefix to remove
	 */
	private $basePath;
	/**
	 * @var bool Whether or not add the base path to the Location header if exists
	 */
	private $fixLocation = false;

	/**
	 * Configure the base path of the request.
	 *
	 * @param string $basePath
	 */
	public function __construct(string $basePath)
	{
		$this->setBasePath($basePath);
	}

	public function setBasePath(string $basePath)
	{
		$this->basePath = '/' . trim($basePath, '/');
	}

	/**
	 * Whether fix the Location header in the response if exists.
	 *
	 * @param bool $fixLocation
	 *
	 * @return self
	 */
	public function fixLocation(bool $fixLocation = true)
	{
		$this->fixLocation = $fixLocation;

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
		$uri = $request->getUri();
		$request = $request->withUri($uri->withPath($this->removeBasePath($uri->getPath())));

		$response = $delegate->process($request);

		if ($this->fixLocation && $response->hasHeader('Location')) {
			$location = Factory::uri($response->getHeaderLine('Location'));

			$host = $location->getHost();
			if ($host === '' || $host === $uri->getHost()) {
				$location = $location->withPath($this->addBasePath($location->getPath()));

				return $response->withHeader('Location', (string) $location);
			}
		}

		return $response;
	}

	/**
	 * Removes the basepath from a path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function removeBasePath(string $path)
	{
		if (strpos($path, $this->basePath) === 0) {
			$path = substr($path, strlen($this->basePath)) ?: '';
		}

		if ($path !== '' && $path[0] !== '/') {
			return '/' . $path;
		}

		return $path;
	}

	/**
	 * Adds the basepath to a path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function addBasePath($path)
	{
		if (strpos($path, $this->basePath) === 0) {
			return $path;
		}

		return str_replace('//', '/', $this->basePath . '/' . $path);
	}
}