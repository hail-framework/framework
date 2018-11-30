<?php

declare(strict_types=1);

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\ExceptionInterface;
use Hail\Http\Client\Exception\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class MultiCurl extends AbstractCurl implements BatchClientInterface, ClientInterface
{
    private $queue = [];
    private $curlm;

    public function __construct(array $options = [])
    {
        static::$default['callback'] = function (
            RequestInterface $request,
            ResponseInterface $response = null,
            ClientException $e = null
        ) {
        };
        static::$types['callback'] = 'callable';

        parent::__construct($options);
    }

    /**
     * Populates the supplied response with the response for the supplied request.
     *
     * The array of options will be passed to curl_setopt_array().
     *
     * If a "callback" option is supplied, its value will be called when the
     * request completes. The callable should have the following signature:
     *
     *     $callback = function($request, $response, $exception) {
     *         if (!$exception) {
     *             // success
     *         } else {
     *             // error ($error is one of the CURLE_* constants)
     *         }
     *     };
     *
     * @param RequestInterface $request
     * @param array            $options
     */
    public function sendAsyncRequest(RequestInterface $request, array $options = []): void
    {
        $options = $this->validateOptions($options);

        $this->queue[] = [$request, $options];
    }

    public function sendRequest(RequestInterface $request, array $options = []): ResponseInterface
    {
        $options = $this->validateOptions($options);
        $originalCallback = $options['callback'];
        $responseToReturn = null;
        $options['callback'] = function (
            RequestInterface $request,
            ResponseInterface $response = null,
            ClientException $e = null
        ) use (&$responseToReturn, $originalCallback) {
            $responseToReturn = $response;
            $originalCallback($request, $response, $e);
        };

        $this->queue[] = [$request, $options];
        $this->flush();

        return $responseToReturn;
    }

    public function count(): int
    {
        return \count($this->queue);
    }

    /**
     * @throws ClientException
     */
    public function flush(): void
    {
        while (!empty($this->queue)) {
            $this->proceed();
        }
    }

    /**
     * @throws ClientException
     */
    public function proceed(): void
    {
        if (empty($this->queue)) {
            return;
        }

        if (!$this->curlm) {
            if (false === $this->curlm = \curl_multi_init()) {
                throw new ClientException('Unable to create a new cURL multi handle');
            }
        }

        foreach ($this->queue as $i => $queueItem) {
            if (2 !== \count($queueItem)) {
                // We have already prepared this curl
                continue;
            }
            // prepare curl handle
            /** @var $request RequestInterface */
            /** @var $options array */
            [$request, $options] = $queueItem;

            $curl = $this->createHandle();
            $response = $this->prepare($curl, $request, $options);
            $this->queue[$i][] = $curl;
            $this->queue[$i][] = $response;
            \curl_multi_add_handle($this->curlm, $curl);
        }

        // process outstanding perform
        $active = null;
        do {
            $mrc = \curl_multi_exec($this->curlm, $active);
        } while (\CURLM_CALL_MULTI_PERFORM == $mrc);

        $exception = null;

        // handle any completed requests
        while ($active && \CURLM_OK == $mrc) {
            \curl_multi_select($this->curlm);
            do {
                $mrc = \curl_multi_exec($this->curlm, $active);
            } while (\CURLM_CALL_MULTI_PERFORM == $mrc);

            while ($info = \curl_multi_info_read($this->curlm)) {
                if (\CURLMSG_DONE == $info['msg']) {
                    $handled = false;
                    foreach (array_keys($this->queue) as $i) {
                        /** @var $request RequestInterface */
                        /** @var $options array */
                        /** @var $response ResponseInterface */
                        list($request, $options, $curl, $response) = $this->queue[$i];

                        // Try to find the correct handle from the queue.
                        if ($curl !== $info['handle']) {
                            continue;
                        }

                        try {
                            $handled = true;
                            $this->parseError($request, $info['result'], $curl);
                            $response->getBody()->rewind();
                        } catch (ExceptionInterface $e) {
                            if (null === $exception) {
                                $exception = $e;
                            }
                        }

                        // remove from queue
                        \curl_multi_remove_handle($this->curlm, $curl);
                        $this->releaseHandle($curl);
                        unset($this->queue[$i]);

                        // callback
                        $options['callback']($request, $response, $exception);
                    }

                    if (!$handled) {
                        throw new ClientException('Not support server push now');
                    }
                }
            }
        }

        // cleanup
        if (empty($this->queue)) {
            \curl_multi_close($this->curlm);
            $this->curlm = null;

            if (null !== $exception) {
                throw $exception;
            }
        }
    }
}
