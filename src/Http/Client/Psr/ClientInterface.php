<?php
namespace Hail\Http\Client\Psr;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientInterface
{
    /**
     * Sends a PSR-7 request.
     *
     * If a request is sent without any prior configuration, an exception MUST NOT be thrown
     * when a response is recieved, no matter the HTTP status code.
     *
     * If a request is sent without any prior configuration, a HTTP client MUST NOT follow redirects.
     *
     * The client MAY do modifications to the Request before sending it. Because PSR-7 objects are
     * immutable, one cannot assume that the object passed to Client::sendRequest will be the same
     * object that is actually sent. For example the Request object that is returned by an exception MAY
     * be a different object than the one passed to sendRequest, so comparison by reference (===) is not possible.
     *
     * {@link https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects}
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws ClientException If an error happens during processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface;
}