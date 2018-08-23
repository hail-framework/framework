<?php

namespace Hail\Http\Client\Curl;

use Hail\Http\Client\Exception\TransferException;
use Hail\Promise\PromiseInterface;
use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Shared promises core.
 *
 * @license http://opensource.org/licenses/MIT MIT
 * @author  Михаил Красильников <m.krasilnikov@yandex.ru>
 */
class PromiseCore
{
    /**
     * HTTP request.
     *
     * @var RequestInterface
     */
    private $request;

    /**
     * cURL handle.
     *
     * @var resource
     */
    private $handle;

    /**
     * Response builder.
     *
     * @var ResponseBuilder
     */
    private $responseBuilder;

    /**
     * Promise state.
     *
     * @var string
     */
    private $state;

    /**
     * Exception.
     *
     * @var ClientException|null
     */
    private $exception;

    /**
     * Functions to call when a response will be available.
     *
     * @var callable[]
     */
    private $onFulfilled = [];

    /**
     * Functions to call when an error happens.
     *
     * @var callable[]
     */
    private $onRejected = [];

    /**
     * Create shared core.
     *
     * @param RequestInterface $request HTTP request
     * @param resource         $handle  cURL handle
     * @param ResponseBuilder  $responseBuilder
     */
    public function __construct(
        RequestInterface $request,
        $handle,
        ResponseBuilder $responseBuilder
    ) {
        if (!\is_resource($handle)) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Parameter $handle expected to be a cURL resource, %s given',
                    \gettype($handle)
                )
            );
        }
        if (\get_resource_type($handle) !== 'curl') {
            throw new \InvalidArgumentException(
                \sprintf(
                    'Parameter $handle expected to be a cURL resource, %s resource given',
                    \get_resource_type($handle)
                )
            );
        }

        $this->request = $request;
        $this->handle = $handle;
        $this->responseBuilder = $responseBuilder;
        $this->state = PromiseInterface::PENDING;
    }

    /**
     * Add on fulfilled callback.
     *
     * @param callable $callback
     */
    public function addOnFulfilled(callable $callback)
    {
        if ($this->getState() === PromiseInterface::PENDING) {
            $this->onFulfilled[] = $callback;
        } elseif ($this->getState() === PromiseInterface::FULFILLED) {
            $response = $callback($this->responseBuilder->getResponse());
            if ($response instanceof ResponseInterface) {
                $this->responseBuilder->setResponse($response);
            }
        }
    }

    /**
     * Add on rejected callback.
     *
     * @param callable $callback
     */
    public function addOnRejected(callable $callback)
    {
        if ($this->getState() === PromiseInterface::PENDING) {
            $this->onRejected[] = $callback;
        } elseif ($this->getState() === PromiseInterface::REJECTED) {
            $this->exception = $callback($this->exception);
        }
    }

    /**
     * Return cURL handle.
     *
     * @return resource
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Get the state of the promise, one of PENDING, FULFILLED or REJECTED.
     *
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * Return request.
     *
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Return the value of the promise (fulfilled).
     *
     * @return ResponseInterface Response Object only when the Promise is fulfilled
     */
    public function getResponse()
    {
        return $this->responseBuilder->getResponse();
    }

    /**
     * Get the reason why the promise was rejected.
     *
     * If the exception is an instance of Http\Client\Exception\HttpException it will contain
     * the response object with the status code and the http reason.
     *
     * @return ClientException Exception Object only when the Promise is rejected
     *
     * @throws \LogicException When the promise is not rejected
     */
    public function getException()
    {
        if (null === $this->exception) {
            throw new \LogicException('Promise is not rejected');
        }

        return $this->exception;
    }

    /**
     * Fulfill promise.
     */
    public function fulfill()
    {
        $this->state = PromiseInterface::FULFILLED;
        $response = $this->responseBuilder->getResponse();
        try {
            $response->getBody()->seek(0);
        } catch (\RuntimeException $e) {
            $exception = new TransferException($e->getMessage(), $e->getCode(), $e);
            $this->reject($exception);

            return;
        }

        while (\count($this->onFulfilled) > 0) {
            $callback = \array_shift($this->onFulfilled);
            $response = $callback($response);
        }

        if ($response instanceof ResponseInterface) {
            $this->responseBuilder->setResponse($response);
        }
    }

    /**
     * Reject promise.
     *
     * @param ClientException $exception Reject reason
     */
    public function reject(ClientException $exception)
    {
        $this->exception = $exception;
        $this->state = PromiseInterface::REJECTED;

        while (\count($this->onRejected) > 0) {
            $callback = \array_shift($this->onRejected);
            try {
                $exception = $callback($this->exception);
                $this->exception = $exception;
            } catch (ClientException $exception) {
                $this->exception = $exception;
            }
        }
    }
}
