<?php

namespace Hail\Exception;


class ForbiddenRequestException extends BadRequestException
{
	/** @var int */
	protected $code = 403;
}