<?php

namespace Hail\Http\Client;

use Hail\Http\Client\Plugin\PluginInterface;
use Hail\Http\Client\Plugin\SendAsyncRequest;
use Hail\Http\Client\Plugin\SendRequest;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The client managing plugins and providing a decorator around HTTP Clients.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class PluginClient implements ClientInterface
{
    /**
     * An HTTP async client.
     *
     * @var ClientInterface
     */
    private $client;

    /**
     * The plugin chain.
     *
     * @var PluginInterface[]
     */
    private $plugins;

    /**
     * A list of options.
     *
     * @var array
     */
    private $options;

    /**
     * @param ClientInterface   $client
     * @param PluginInterface[] $plugins
     * @param array             $options {
     *
     * @var int                 $max_restarts
     * }
     *
     * @throws \RuntimeException if client is not an instance of ClientInterface or AsyncClientInterface
     */
    public function __construct(ClientInterface $client, array $plugins = [], array $options = [])
    {
        $this->client = $client;
        $this->plugins = $plugins;
        $this->options = $options + ['max_restarts' => 10];
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->plugins !== []) {
            static $plugins;
            if ($plugins === null) {
                $plugins = $this->plugins;
                $plugins[] = new SendRequest($this->client);
            }

            $handler = new RequestHandler($plugins, $this->options['max_restarts']);

            return $handler->dispatch($request)->wait();
        }

        return $this->client->sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsyncRequest(RequestInterface $request): PromiseInterface
    {
        if ($this->plugins !== []) {
            static $plugins;
            if ($plugins === null) {
                $plugins = $this->plugins;
                $plugins[] = new SendAsyncRequest($this->client);
            }

            $handler = new RequestHandler($plugins, $this->options['max_restarts']);

            return $handler->dispatch($request);
        }

        return $this->client->sendAsyncRequest($request);
    }
}
