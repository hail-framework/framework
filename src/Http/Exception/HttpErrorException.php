<?php

namespace Hail\Http\Exception;

use Hail\Http\Message\Response;

class HttpErrorException extends \Exception
{
	private $context = [];

	/**
	 * Create and returns a new instance
	 *
	 * @param int $code A valid http error code
	 * @param array $context
	 * @param \Exception|\Throwable|null $previous
	 *
	 * @return static
	 */
	public static function create($code = 500, array $context = [], $previous = null)
	{
		if (!isset(Response::$phrases[$code])) {
			throw new \RuntimeException("Http error not valid ({$code})");
		}

		$exception = new static(Response::$phrases[$code], $code, $previous);
		$exception->setContext($context);

		return $exception;
	}

	/**
	 * Add data context used in the error handler
	 *
	 * @param array $context
	 */
	public function setContext(array $context)
	{
		$this->context = $context;
	}

	/**
	 * Return the data context
	 *
	 * @return array
	 */
	public function getContext()
	{
		return $this->context;
	}
}