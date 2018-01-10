<?php

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\RequestException;
use Hail\Http\Matcher\Factory;
use Hail\Promise\PromiseInterface;
use Hail\Http\Matcher\MatcherInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Route a request to a specific client in the stack based using a RequestMatcher.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class RouterClient implements ClientInterface
{
    /**
     * @var array
     */
    private $clients = [];

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->chooseHttpClient($request)->sendRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsyncRequest(RequestInterface $request): PromiseInterface
    {
        return $this->chooseHttpClient($request)->sendAsyncRequest($request);
    }

    /**
     * Add a client to the router.
     *
     * @param ClientInterface    $client
     * @param MatcherInterface[] $matcher
     */
    public function addClient(ClientInterface $client, array $matcher)
    {
        $this->clients[] = [
            'matcher' => $matcher,
            'client' => $client,
        ];
    }

    /**
     * Choose an HTTP client given a specific request.
     *
     * @param RequestInterface $request
     *
     * @return ClientInterface|ClientInterface
     */
    protected function chooseHttpClient(RequestInterface $request)
    {
        foreach ($this->clients as $client) {
            if (Factory::matches($client['matcher'], $request)) {
                return $client['client'];
            }
        }

        throw new RequestException('No client found for the specified request', $request);
    }
}
