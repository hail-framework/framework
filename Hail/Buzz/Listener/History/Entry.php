<?php

namespace Hail\Buzz\Listener\History;

use Hail\Buzz\Message\MessageInterface;
use Hail\Buzz\Message\RequestInterface;

class Entry
{
    private $request;
    private $response;
    private $duration;

    /**
     * Constructor.
     *
     * @param RequestInterface $request  The request
     * @param MessageInterface $response The response
     * @param integer          $duration The duration in seconds
     */
    public function __construct(RequestInterface $request, MessageInterface $response, $duration = null)
    {
        $this->request = $request;
        $this->response = $response;
        $this->duration = $duration;
    }

    public function getRequest()
    {
        return $this->request;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function getDuration()
    {
        return $this->duration;
    }
}
