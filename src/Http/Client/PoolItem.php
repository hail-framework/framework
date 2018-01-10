<?php

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\RequestException;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\ResponseInterface;

/**
 * A HttpClientPoolItem represent a HttpClient inside a Pool.
 *
 * It is disabled when a request failed and can be reenable after a certain number of seconds
 * It also keep tracks of the current number of request the client is currently sending (only usable for async method)
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class PoolItem implements ClientInterface
{
    /**
     * @var int Number of request this client is currently sending
     */
    private $sendingRequestCount = 0;

    /**
     * @var \DateTime|null Time when this client has been disabled or null if enable
     */
    private $disabledAt;

    /**
     * @var int|null Number of seconds after this client is reenable, by default null: never reenable this client
     */
    private $reenableAfter;

    /**
     * @var ClientInterface A http client responding to async and sync request
     */
    private $client;

    /**
     * @param ClientInterface $client
     * @param null|int        $reenableAfter Number of seconds after this client is reenable
     */
    public function __construct(ClientInterface $client, int $reenableAfter = null)
    {
        $this->client = $client;
        $this->reenableAfter = $reenableAfter;
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->isDisabled()) {
            throw new RequestException('Cannot send the request as this client has been disabled', $request);
        }

        ++$this->sendingRequestCount;

        try {
            return $this->client->sendRequest($request);
        } catch (ClientException $e) {
            $this->disable();
            throw $e;
        } finally {
            --$this->sendingRequestCount;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function sendAsyncRequest(RequestInterface $request): PromiseInterface
    {
        if ($this->isDisabled()) {
            throw new RequestException('Cannot send the request as this client has been disabled', $request);
        }

        ++$this->sendingRequestCount;

        return $this->client->sendAsyncRequest($request)->then(function ($response) {
            --$this->sendingRequestCount;

            return $response;
        }, function ($exception) {
            $this->disable();
            --$this->sendingRequestCount;

            throw $exception;
        });
    }

    /**
     * Whether this client is disabled or not.
     *
     * Will also reactivate this client if possible
     *
     * @return bool
     */
    public function isDisabled(): bool
    {
        $disabledAt = $this->getDisabledAt();

        if (null !== $this->reenableAfter && null !== $disabledAt) {
            // Reenable after a certain time
            $now = new \DateTime();

            if (($now->getTimestamp() - $disabledAt->getTimestamp()) >= $this->reenableAfter) {
                $this->enable();

                return false;
            }

            return true;
        }

        return null !== $disabledAt;
    }

    /**
     * Get current number of request that is send by the underlying http client.
     *
     * @return int
     */
    public function getSendingRequestCount(): int
    {
        return $this->sendingRequestCount;
    }

    /**
     * Return when this client has been disabled or null if it's enabled.
     *
     * @return \DateTime|null
     */
    private function getDisabledAt(): ?\DateTime
    {
        return $this->disabledAt;
    }

    /**
     * Enable the current client.
     */
    private function enable(): void
    {
        $this->disabledAt = null;
    }

    /**
     * Disable the current client.
     */
    private function disable(): void
    {
        $this->disabledAt = new \DateTime('now');
    }
}
