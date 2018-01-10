<?php
namespace Hail\Http\Client\Psr\Exception;

use Psr\Http\Message\RequestInterface;
use Hail\Http\Client\Psr\ClientException;

/**
 * Thrown when the request cannot be completed because of network issues.
 *
 * There is no response object as this exception is thrown when no response has been received.
 *
 * Example: the target host name can not be resolved or the connection failed.
 */
interface NetworkException extends ClientException
{
    /**
     * Returns the request.
     *
     * The request object MAY be a different object from the one passed to Client::sendRequest()
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface;
}