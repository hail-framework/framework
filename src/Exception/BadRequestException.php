<?php

namespace Hail\Exception;

/**
 * Class BadRequestException
 * @package Hail\Exception
 */
class BadRequestException extends ApplicationException
{
	/** @var int */
	protected $code = 404;

	public function __construct($message = '', $code = 0, \Exception $previous = NULL)
	{
		parent::__construct($message, $code < 200 || $code > 504 ? $this->code : $code, $previous);
	}
}