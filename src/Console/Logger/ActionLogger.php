<?php

namespace Hail\Console\Logger;

use Hail\Console\Formatter;

class ActionLogger
{
    public $fd;

    public $formatter;

    public function __construct($fd = null)
    {
        $this->fd = $fd ?: \fopen('php://stderr', 'w');
        $this->formatter = Formatter::getInstance();
    }

    public function __destruct()
    {
        if ($this->fd) {
            \fclose($this->fd);
        }
    }

    public function newAction($title, $desc = '', $status = 'waiting')
    {
        return new LogAction($this, $title, $desc, $status);
    }
}
