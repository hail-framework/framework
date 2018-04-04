<?php

namespace Hail\Exception;

use Throwable;

class ActionError extends ActionForward
{
    public function __construct(string $message = '', int $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->setForwardTo([
            'handler' => [
                'controller' => 'Error',
                'action' => 'index',
            ],
            'params' => [
                'error' => $this,
            ],
        ]);
    }
}