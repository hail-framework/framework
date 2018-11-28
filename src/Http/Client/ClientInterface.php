<?php

namespace Hail\Http\Client;

use Hail\Promise\PromiseInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface as PsrClient;
use Psr\Http\Message\RequestInterface;

/**
 * @author Feng Hao <flyinghail@msn.com>
 */
interface ClientInterface extends PsrClient
{
    /**
     * Sends a PSR-7 request in an asynchronous way.
     *
     * Exceptions related to processing the request are available from the returned Promise.
     *
     * @param RequestInterface $request
     *
     * @return PromiseInterface Resolves a PSR-7 Response or fails with an Http\Client\Exception.
     *
     * @throws ClientExceptionInterface If processing the request is impossible (eg. bad configuration).
     */
    public function sendAsyncRequest(RequestInterface $request): PromiseInterface;
}