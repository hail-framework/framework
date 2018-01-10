<?php

namespace Hail\Http\Client;

use Hail\Http\Client\Plugin\PluginInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;

interface RequestHandlerInterface
{
    /**
     * Handle the request and return a response.
     *
     * @param RequestInterface $request
     *
     * @return PromiseInterface
     */
    public function handle(RequestInterface $request): PromiseInterface;

    /**
     * @param RequestInterface $request
     *
     * @return PromiseInterface
     */
    public function restart(RequestInterface $request): PromiseInterface;

    /**
     * @param PluginInterface $plugin
     */
    public function insertAfter(PluginInterface $plugin): void;
}