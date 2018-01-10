<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\Exception\ClientErrorException;
use Hail\Http\Client\Exception\ServerErrorException;
use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Throw exception when the response of a request is not acceptable.
 *
 * Status codes 400-499 lead to a ClientErrorException, status 500-599 to a ServerErrorException.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class ErrorPlugin implements PluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        return $handler->handle($request)->then(function (ResponseInterface $response) use ($request) {
            return $this->transformResponseToException($request, $response);
        });
    }

    /**
     * Transform response to an error if possible.
     *
     * @param RequestInterface  $request  Request of the call
     * @param ResponseInterface $response Response of the call
     *
     * @throws ClientErrorException If response status code is a 4xx
     * @throws ServerErrorException If response status code is a 5xx
     *
     * @return ResponseInterface If status code is not in 4xx or 5xx return response
     */
    protected function transformResponseToException(RequestInterface $request, ResponseInterface $response)
    {
        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            throw new ClientErrorException($response->getReasonPhrase(), $request, $response);
        }

        if ($response->getStatusCode() >= 500 && $response->getStatusCode() < 600) {
            throw new ServerErrorException($response->getReasonPhrase(), $request, $response);
        }

        return $response;
    }
}
