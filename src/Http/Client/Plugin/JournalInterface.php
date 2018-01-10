<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Records history of HTTP calls.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
interface JournalInterface
{
    /**
     * Record a successful call.
     *
     * @param RequestInterface  $request  Request use to make the call
     * @param ResponseInterface $response Response returned by the call
     */
    public function addSuccess(RequestInterface $request, ResponseInterface $response);

    /**
     * Record a failed call.
     *
     * @param RequestInterface $request   Request use to make the call
     * @param ClientException  $exception Exception returned by the call
     */
    public function addFailure(RequestInterface $request, ClientException $exception);
}
