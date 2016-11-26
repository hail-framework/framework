<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 11:21
 */

namespace Hail\Exception;


class ForbiddenRequestException extends BadRequestException
{
	/** @var int */
	protected $code = 403;
}