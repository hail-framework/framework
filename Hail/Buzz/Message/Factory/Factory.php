<?php

namespace Hail\Buzz\Message\Factory;

use Hail\Buzz\Message\Form\FormRequest;
use Hail\Buzz\Message\Request;
use Hail\Buzz\Message\RequestInterface;
use Hail\Buzz\Message\Response;

class Factory implements FactoryInterface
{
    public function createRequest($method = RequestInterface::METHOD_GET, $resource = '/', $host = null)
    {
        return new Request($method, $resource, $host);
    }

    public function createFormRequest($method = RequestInterface::METHOD_POST, $resource = '/', $host = null)
    {
        return new FormRequest($method, $resource, $host);
    }

    public function createResponse()
    {
        return new Response();
    }
}
