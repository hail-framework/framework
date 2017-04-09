<?php

namespace Hail\Http\Middleware;

use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class TrailingSlash implements MiddlewareInterface
{
	/**
	 * @var bool Add or remove the slash
	 */
	private $trailingSlash;
	/**
	 * @var bool Returns a redirect response or not
	 */
	private $redirect = false;

	/**
	 * Configure whether add or remove the slash.
	 *
	 * @param bool $trailingSlash
	 */
	public function __construct(bool $trailingSlash = false)
	{
		$this->trailingSlash = $trailingSlash;
	}

	/**
	 * Whether returns a 301 response to the new path.
	 *
	 * @param bool $redirect
	 *
	 * @return self
	 */
	public function redirect(bool $redirect = true)
	{
		$this->redirect = $redirect;

		return $this;
	}

	/**
	 * Process a request and return a response.
	 *
	 * @param ServerRequestInterface $request
	 * @param DelegateInterface      $delegate
	 *
	 * @return ResponseInterface
	 */
	public function process(ServerRequestInterface $request, DelegateInterface $delegate)
	{
		$uri = $request->getUri();
		$uriPath = $uri->getPath();
		$path = $this->normalize($uriPath);

		if ($this->redirect && $uriPath !== $path) {
			return Factory::response(301)
				->withHeader('Location', (string) $uri->withPath($path));
		}

		return $delegate->process($request->withUri($uri->withPath($path)));
	}

	/**
	 * Normalize the trailing slash.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function normalize(string $path): string
	{
		if ($path === '') {
			return '/';
		}

		if ($this->trailingSlash) {
			if ($path[$len = (strlen($path) - 1)] !== '/' &&
				(
					($pos = strrpos($path, '.')) === false ||
					($pos < $len && $pos < strrpos($path, '/'))
				)
			) {
				return $path . '/';
			}
		} else {
			return rtrim($path, '/');
		}

		return $path;
	}
}