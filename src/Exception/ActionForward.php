<?php

namespace Hail\Exception;


class ActionForward extends ApplicationException
{
    private $handler;

    private $params;

    public function setForwardTo(array $to)
    {
        $this->handler = $to['handler'];
        $this->params = $to['params'];
    }

    public function getForwardTo()
    {
        return [
            'handler' => $this->handler,
            'params' => $this->params,
        ];
    }

    public function getHandler()
    {
        return $this->handler;
    }

    public function getParams()
    {
        return $this->params;
    }
}