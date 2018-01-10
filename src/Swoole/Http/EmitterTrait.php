<?php

namespace Hail\Swoole\Http;

use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response;

trait EmitterTrait
{
    protected static function emitStatusLine(ResponseInterface $response)
    {
        Server::getResponse()->status(
            $response->getStatusCode()
        );
    }

    protected static function emitHeaders(ResponseInterface $response)
    {
        $serverResponse = Server::getResponse();
        foreach ($response->getHeaders() as $header => $values) {
            $serverResponse->header($header, \implode("\r\n$header: ", $values));
        }
    }
}