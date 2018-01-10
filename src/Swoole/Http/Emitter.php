<?php

namespace Hail\Swoole\Http;

use Hail\Http\Emitter\Sapi;
use Psr\Http\Message\ResponseInterface;

class Emitter extends Sapi
{
    use EmitterTrait;

    protected static function emitBody(ResponseInterface $response)
    {
        $body = (string) $response->getBody();

        Server::getResponse()->end($body);
    }
}