<?php

namespace Hail\Buzz\Exception;

use Hail\Buzz\Message\RequestInterface;

class RequestException extends ClientException
{
    /**
     * Request object
     *
     * @var RequestInterface
     */
    private $request;

    /**
     * @return RequestInterface
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @param RequestInterface $request
     */
    public function setRequest(RequestInterface $request)
    {
        $this->request = $request;
    }

}