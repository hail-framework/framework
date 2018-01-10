<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\ClientInterface;
use Hail\Promise\PromiseInterface;
use Hail\Http\Client\RequestHandlerInterface;
use Psr\Http\Message\RequestInterface;

final class SendAsyncRequest implements PluginInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @param RequestInterface        $request
     * @param RequestHandlerInterface $handler
     *
     * @return PromiseInterface
     * @throws \Exception
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        return $this->client->sendAsyncRequest($request);
    }
}