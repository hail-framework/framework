<?php

namespace Hail\Database\Migration;


use Hail\Console\Command;
use Hail\Console\Component\Prompter;
use Hail\Console\Logger;

trait CommandIO
{
    /**
     * @var Command
     */
    protected $command;

    /**
     * Sets the console command.
     *
     * @param Command $command
     *
     * @return self
     */
    public function setCommand(Command $command)
    {
        $this->command = $command;

        return $this;
    }

    public function getCommand(): Command
    {
        return $this->command;
    }

    public function getInput($key)
    {
        return $this->command->getOption($key);
    }

    public function getOutput(): Logger
    {
        return $this->command->getOutput();
    }

    public function getPrompter(): Prompter
    {
        return $this->command->getPrompter();
    }
}