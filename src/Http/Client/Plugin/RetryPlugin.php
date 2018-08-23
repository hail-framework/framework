<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Hail\Promise\Promise;
use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Retry the request if an exception is thrown.
 *
 * By default will retry only one time.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class RetryPlugin implements PluginInterface
{
    /**
     * Number of retry before sending an exception.
     *
     * @var int
     */
    private $retry = 1;

    /**
     * @var callable
     */
    private $delay = __CLASS__ . '::defaultDelay';

    /**
     * @var callable
     */
    private $decider = __CLASS__ . '::defaultDecider';

    /**
     * Store the retry counter for each request.
     *
     * @var array
     */
    private $retryStorage = [];

    /**
     * @param array  $config  {
     *
     * @var int      $retries Number of retries to attempt if an exception occurs before letting the exception bubble up.
     * @var callable $decider A callback that gets a request and an exception to decide after a failure whether the request should be retried.
     * @var callable $delay   A callback that gets a request, an exception and the number of retries and returns how many microseconds we should wait before trying again.
     * }
     */
    public function __construct(array $config = [])
    {
        if (isset($config['retries']) && \is_int($config['retries'])) {
            $this->retry = $config['retries'];
        }

        if (isset($config['decider']) && \is_callable($config['retries'])) {
            $this->decider = $config['decider'];
        }

        if (isset($config['delay']) && \is_callable($config['retries'])) {
            $this->delay = $config['delay'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        $chainIdentifier = \spl_object_hash($handler);

        $promise = $handler->handle($request);
        $deferred = new Promise(function () use ($promise) {
            $promise->wait(false);
        });

        $onFulfilled = function (ResponseInterface $response) use ($chainIdentifier, $deferred) {
            if (isset($this->retryStorage[$chainIdentifier])) {
                unset($this->retryStorage[$chainIdentifier]);
            }

            $deferred->resolve($response);
            return $response;
        };

        $onRejected = function (ClientException $exception) use ($request, $handler, $onFulfilled, &$onRejected, $chainIdentifier, $deferred) {
            if (!isset($this->retryStorage[$chainIdentifier])) {
                $this->retryStorage[$chainIdentifier] = 0;
            }

            if ($this->retryStorage[$chainIdentifier] >= $this->retry) {
                unset($this->retryStorage[$chainIdentifier]);
                $deferred->reject($exception);
                throw $exception;
            }

            if (!($this->decider)($request, $exception)) {
                throw $exception;
            }

            $time = ($this->delay)($request, $exception, $this->retryStorage[$chainIdentifier]);
            if ($time > 0) {
                \usleep($time);
            }

            // Retry in synchrone
            ++$this->retryStorage[$chainIdentifier];
            $handler->handle($request)->then($onFulfilled, $onRejected);

            throw $exception;
        };

        $promise->then($onFulfilled, $onRejected);

        return $deferred;
    }

    /**
     * @param RequestInterface $request
     * @param ClientException  $e
     * @param int              $retries The number of retries we made before. First time this get called it will be 0.
     *
     * @return int
     */
    public static function defaultDelay(RequestInterface $request, ClientException $e, $retries)
    {
        return (2 ** $retries) * 500000;
    }

    public static function defaultecider(RequestInterface $request, ClientException $e)
    {
        return true;
    }
}
