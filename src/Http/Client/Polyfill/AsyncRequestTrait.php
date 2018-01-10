<?php

namespace Hail\Http\Client\Polyfill;

use Hail\Promise\PromiseInterface;
use Hail\Promise\Util as Promise;
use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Trait AsyncRequestTrait
 *
 * @package Hail\Http\Client\Polyfill
 * @method ResponseInterface sendRequest(RequestInterface $request)
 */
trait AsyncRequestTrait
{
    public function sendAsyncRequest(RequestInterface $request): PromiseInterface
    {
        try {
            return Promise::promise($this->sendRequest($request));
        } catch (ClientException $e) {
            return Promise::rejection($e);
        }
    }
}