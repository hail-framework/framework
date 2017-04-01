<?php

declare(strict_types=1);

namespace Hail\Http\Factory;

use Psr\Http\Message\ServerRequestFactoryInterface;
use Hail\Http\Factory;

/**
 * @author Hao Feng <flyinghail@msn.com>
 */
class ServerRequestFactory implements ServerRequestFactoryInterface
{
    public function createServerRequest($method = null, $uri = null)
    {
        return Factory::serverRequest($method, $uri);
    }

	public function createServerRequestFromArray(array $server)
	{
		return Factory::serverRequest($server);
	}
}
