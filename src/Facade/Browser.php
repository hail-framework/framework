<?php

namespace Hail\Facade;

use Hail;
use Hail\Http\Client\ClientInterface;
use Hail\Http\Client\Middleware\MiddlewareInterface;
use Psr\Http\Message\{
    RequestInterface,
    ResponseInterface
};

/**
 * Class Browser
 *
 * @package Hail\Facade
 * @see \Hail\Http\Browser
 *
 * @method static ResponseInterface get(string $url, array $headers = [])
 * @method static ResponseInterface post(string $url, string $body = '', array $headers = [])
 * @method static ResponseInterface head(string $url, array $headers = [])
 * @method static ResponseInterface patch(string $url, string $body = '', array $headers = [])
 * @method static ResponseInterface put(string $url, string $body = '', array $headers = [])
 * @method static ResponseInterface delete(string $url, string $body = '', array $headers = [])
 * @method static ResponseInterface request(string $method, string $url, array $headers = [], string $body = '')
 * @method static ResponseInterface submitForm(string $url, array $fields, string $method = 'POST', array $headers = [])
 * @method static ResponseInterface sendRequest(RequestInterface $request, array $options = [])
 * @method static RequestInterface|null getLastRequest()
 * @method static ResponseInterface|null getLastResponse()
 * @method static ClientInterface getClient()
 * @method static void addMiddleware(MiddlewareInterface $middleware)
 *
 */
class Browser extends Facade
{
}