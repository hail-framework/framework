<?php
namespace Hail\Http\Client\Psr\Exception;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Hail\Http\Client\Psr\ClientException;

/**
 * Thrown when a response was received but the request itself failed.
 *
 * This exception MAY be thrown on HTTP response codes 4xx and 5xx.
 * This exception MUST NOT be thrown when using the client's default configuration.
 */
interface HttpException extends ClientException
{
    /**
     * Returns the request.
     *
     * The request object MAY be a different object from the one passed to Client::sendRequest()
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface;

    /**
     * Returns the response.
     *
     * @return ResponseInterface
     */
    public function getResponse(): ResponseInterface;
}