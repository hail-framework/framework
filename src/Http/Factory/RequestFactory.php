<?php

declare(strict_types=1);

namespace Hail\Http\Factory;

use Interop\Http\Factory\RequestFactoryInterface;
use Hail\Http\Factory;

/**
 * @author Hao Feng <flyinghail@msn.com>
 */
class RequestFactory implements RequestFactoryInterface
{
	public function createRequest($method, $uri)
	{
		return Factory::request($method, $uri);
	}
}
