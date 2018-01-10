<?php

namespace Hail\Http\Client;

use Hail\Promise\PromiseInterface;
use Hail\Http\Client\Psr\ClientException;
use Hail\Http\Client\Psr\ClientInterface as PsrClient;
use Psr\Http\Message\RequestInterface;

/**
 * @author Hao Feng <flyinghail@msn.com>
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
     * @throws ClientException If processing the request is impossible (eg. bad configuration).
     */
    public function sendAsyncRequest(RequestInterface $request): PromiseInterface;
}