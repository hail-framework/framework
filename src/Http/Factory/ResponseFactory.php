<?php

declare(strict_types=1);

namespace Hail\Http\Factory;

use Interop\Http\Factory\ResponseFactoryInterface;
use Hail\Http\Factory;

/**
 * @author Hao Feng <flyinghail@msn.com>
 */
class ResponseFactory implements ResponseFactoryInterface
{
	public function createResponse($code = 200)
	{
		return Factory::response((int) $code);
	}
}
