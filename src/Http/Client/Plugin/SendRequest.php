<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\ClientInterface;
use Hail\Promise\Util as Promise;
use Hail\Promise\PromiseInterface;
use Hail\Http\Client\RequestHandlerInterface;
use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\RequestInterface;

final class SendRequest implements PluginInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        try {
            return Promise::promise($this->client->sendRequest($request));
        } catch (ClientException $exception) {
            return Promise::rejection($exception);
        }
    }
}