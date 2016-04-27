<?php

namespace Hail\Buzz\Client;

use Hail\Buzz\Exception\ClientException;
use Hail\Buzz\Message\MessageInterface;
use Hail\Buzz\Message\RequestInterface;

interface ClientInterface
{
    /**
     * Populates the supplied response with the response for the supplied request.
     *
     * @param RequestInterface $request  A request object
     * @param MessageInterface $response A response object
     *
     * @throws ClientException If something goes wrong
     */
    public function send(RequestInterface $request, MessageInterface $response);
}
