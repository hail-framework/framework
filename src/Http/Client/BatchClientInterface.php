<?php

declare(strict_types=1);

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\ClientException;
use Psr\Http\Message\RequestInterface;

/**
 * A client capable of running batches of requests.
 *
 * The Countable implementation should return the number of queued requests.
 */
interface BatchClientInterface extends \Countable
{
    /**
     * @param RequestInterface $request
     * @param array            $options
     */
    public function sendAsyncRequest(RequestInterface $request, array $options = []): void;

    /**
     * Processes all queued requests.
     *
     * @throws ClientException If something goes wrong
     */
    public function flush(): void;

    /**
     * Processes zero or more queued requests.
     *
     * @throws ClientException If something goes wrong
     */
    public function proceed(): void;
}
