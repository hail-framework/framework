<?php

namespace Hail\Swoole\Http;

use Hail\Http\Emitter\SapiStream;
use Psr\Http\Message\ResponseInterface;


class EmitterFile extends SapiStream
{
    use EmitterTrait;

    protected static function emitBody(ResponseInterface $response, $maxBufferLength)
    {
        Server::getResponse()->sendfile(
            $response->getBody()->getMetadata('uri')
        );
    }

    protected static function emitBodyRange(array $range, ResponseInterface $response, $maxBufferLength)
    {
        [$unit, $first, $last] = $range;
        $length = $last - $first + 1;

        Server::getResponse()->sendfile(
            $response->getBody()->getMetadata('uri'), $first, $length
        );
    }
}
