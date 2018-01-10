<?php
namespace Hail\Console\Exception;

use Exception;
use Hail\Console\CommandInterface;

class CommandBaseException extends Exception
{
    public $command;

    public function __construct(CommandInterface $command, $message = "", $code = 0, $previous = null)
    {
        $this->command = $command;
        parent::__construct($message, $code, $previous);
    }

    public function getCommand()
    {
        return $this->command;
    }
}
