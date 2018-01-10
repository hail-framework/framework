<?php

namespace Hail\Http\Client\Message;

use Psr\Http\Message\RequestInterface;

/**
 * Authenticate a PSR-7 Request.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
interface AuthenticationInterface
{
    /**
     * Authenticates a request.
     *
     * @param RequestInterface $request
     *
     * @return RequestInterface
     */
    public function authenticate(RequestInterface $request): RequestInterface;
}
