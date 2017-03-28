<?php
namespace Psr\Http\Message;

interface RequestFactoryInterface
{
	/**
	 * Create a new request.
	 *
	 * @param string $method
	 * @param UriInterface|string $uri
	 *
	 * @return RequestInterface
	 */
	public function createRequest($method, $uri);
}