<?php

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\HttpClientNotFoundException;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A http client pool allows to send requests on a pool of different http client using a specific strategy (least used,
 * round robin, random).
 */
class Pool implements ClientInterface
{
    public const RANDOM = 1,
        ROUND_ROBIN = 2,
        LEAST_USED = 3;

    /**
     * @var int
     */
    private $rule;

    /**
     * @var PoolItem[]
     */
    protected $clientPool = [];

    public function __construct(int $rule)
    {
        $this->rule = $rule;
    }

    /**
     * Add a client to the pool.
     *
     * @param ClientInterface|PoolItem $client
     */
    public function addHttpClient($client)
    {
        if (!$client instanceof PoolItem) {
            $client = new PoolItem($client);
        }

        $this->clientPool[] = $client;
    }

    /**
     * Return an http client given a specific strategy.
     *
     * @return PoolItem Return a http client that can do both sync or async
     * @throws HttpClientNotFoundException When no http client has been found into the pool
     */
    protected function chooseHttpClient(): PoolItem
    {
        $clientPool = [];
        foreach ($this->clientPool as $k => $poolItem) {
            if (!$poolItem->isDisabled()) {
                $clientPool[$k] = $poolItem;
            }
        }

        if ($clientPool === []) {
            throw new HttpClientNotFoundException('Cannot choose a http client as there is no one present in the pool');
        }

        switch ($this->rule) {
            case static::ROUND_ROBIN:
                static $index = 0;

                $keys = \array_keys($clientPool);
                if ($index > \end($keys)) {
                    $index = \reset($keys);
                } else {
                    foreach ($keys as $k) {
                        if ($index <= $k) {
                            $index = $k;
                            break;
                        }
                    }
                }

                return $clientPool[$index];

            //This strategy is only useful when doing async request
            case static::LEAST_USED:
                \usort($clientPool, function (PoolItem $clientA, PoolItem $clientB) {
                    return $clientA->getSendingRequestCount() <=> $clientB->getSendingRequestCount();
                });

                return \reset($clientPool);

            case static::RANDOM:
            default:
                return $clientPool[\array_rand($clientPool)];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsyncRequest(RequestInterface $request): PromiseInterface
    {
        return $this->chooseHttpClient()->sendAsyncRequest($request);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->chooseHttpClient()->sendRequest($request);
    }
}
