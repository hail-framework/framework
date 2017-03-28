<?php
namespace Psr\Http\Message;

interface ResponseFactoryInterface
{
	/**
	 * Create a new response.
	 *
	 * @param integer $code HTTP status code
	 *
	 * @return ResponseInterface
	 */
	public function createResponse($code = 200);
}