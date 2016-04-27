<?php

namespace Hail\Buzz\Listener;

use Hail\Buzz\Message\MessageInterface;
use Hail\Buzz\Message\RequestInterface;

class BasicAuthListener implements ListenerInterface
{
    private $username;
    private $password;

    public function __construct($username, $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function preSend(RequestInterface $request)
    {
        $request->addHeader('Authorization: Basic '.base64_encode($this->username.':'.$this->password));
    }

    public function postSend(RequestInterface $request, MessageInterface $response)
    {
    }
}
