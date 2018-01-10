<?php
namespace Hail\Console\Exception;

use Hail\Console\CommandInterface;

class CommandNotFoundException extends CommandBaseException
{
    public $name;

    public function __construct(CommandInterface $command, $name)
    {
        $this->name = $name;
        parent::__construct($command, "Command $name not found.");
    }
}
