<?php

namespace Hail\Exception;


class ActionForward extends ApplicationException
{
    private $to;

    public function setForwardTo(array $to)
    {
        $this->to = $to;
    }

    public function getForwardTo()
    {
        return $this->to;
    }
}