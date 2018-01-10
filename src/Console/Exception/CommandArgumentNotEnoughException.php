<?php
namespace Hail\Console\Exception;

use Hail\Console\CommandInterface;

class CommandArgumentNotEnoughException extends CommandBaseException
{
    public $given;

    public $required;

    public function __construct(CommandInterface $command, $given, $required)
    {
        $this->given = $given;
        $this->required = $required;
        parent::__construct($command, "Insufficient arguments for command '{$command->name()}', which requires $required arguments, $given given.");
    }
}
