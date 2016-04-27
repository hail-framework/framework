<?php

namespace Hail\Buzz\Listener;

use Hail\Buzz\Message\MessageInterface;
use Hail\Buzz\Message\RequestInterface;

interface ListenerInterface
{
    public function preSend(RequestInterface $request);
    public function postSend(RequestInterface $request, MessageInterface $response);
}
