<?php

namespace Hail\Http\Client\Polyfill;

use Hail\Http\Client\Exception\BatchException;
use Hail\Promise\PromiseInterface;
use Hail\Promise\Util as Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Hail\Http\Client\Psr\ClientException;

/**
 * BatchRequest allow to sends multiple request and retrieve a Batch Result.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 * @author Feng Hao <flyinghail@msn.com>
 *
 * @method ResponseInterface sendRequest(RequestInterface $request)
 * @method PromiseInterface sendAsyncRequest(RequestInterface $request)
 */
trait BatchRequestTrait
{
    /**
     * Send several requests.
     *
     * You may not assume that the requests are executed in a particular order. If the order matters
     * for your application, use sendRequest sequentially.
     *
     * @param RequestInterface[] The requests to send
     *
     * @return BatchResult Containing one result per request
     *
     * @throws BatchException If one or more requests fails. The exception gives access to the
     *                        BatchResult with a map of request to result for success, request to
     *                        exception for failures
     */
    public function sendRequests(array $requests): BatchResult
    {
        $batchResult = new BatchResult();

        foreach ($requests as $request) {
            try {
                $response = $this->sendRequest($request);
                $batchResult = $batchResult->addResponse($request, $response);
            } catch (ClientException $e) {
                $batchResult = $batchResult->addException($request, $e);
            }
        }

        if ($batchResult->hasExceptions()) {
            throw new BatchException($batchResult);
        }

        return $batchResult;
    }

    public function sendAsyncRequests(array $requests): PromiseInterface
    {
        $promises = [];
        foreach ($requests as $request) {
            $promises[] = $this->sendAsyncRequests($request);
        }

        $batchResult = new BatchResult();

        return Promise::each(
            $promises,
            static function ($response, $idx) use (&$batchResult, $requests) {
                $batchResult = $batchResult->addResponse($requests[$idx], $response);
            },
            static function ($reason, $idx, Promise $aggregate) use (&$batchResult, $requests) {
                $batchResult = $batchResult->addException($requests[$idx], $reason);
            }
        )->then(
            static function () use (&$batchResult) {
                return $batchResult;
            },
            static function () use ($batchResult) {
                throw new BatchException($batchResult);
            }
        );
    }
}
