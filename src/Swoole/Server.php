<?php

namespace Hail\Swoole;


use Hail\Swoole\Http\Server as HttpServer;

/**
 * Class Server
 *
 * @package Hail\Swoole
 * @property-read HttpServer $http
 */
class Server
{
    private const SERVERS = [
        'http' => HttpServer::class,
    ];

    public function __get(string $name)
    {
        if (!isset(self::SERVERS[$name])) {
            throw new \RuntimeException('Server not defined: ' . $name);
        }

        return $this->$name = new (self::SERVERS[$name])();
    }
}