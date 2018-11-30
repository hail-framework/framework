<?php

declare(strict_types=1);

namespace Hail\Http\Client;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Curl extends AbstractCurl implements ClientInterface
{
    public function sendRequest(RequestInterface $request, array $options = []): ResponseInterface
    {
        $options = $this->validateOptions($options);
        $curl = $this->createHandle();
        $response = $this->prepare($curl, $request, $options);

        try {
            \curl_exec($curl);
            $this->parseError($request, \curl_errno($curl), $curl);
        } finally {
            $this->releaseHandle($curl);
        }

        $response->getBody()->rewind();

        return $response;
    }
}
