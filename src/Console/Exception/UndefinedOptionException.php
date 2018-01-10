<?php

namespace Hail\Console\Exception;

use Hail\Console\CommandInterface;
use Hail\Console\Option\OptionCollection;

class UndefinedOptionException extends \Exception
{
    /**
     * @var OptionCollection
     */
    public $options;

    /**
     * @var CommandInterface
     */
    public $command;

    public function __construct($message, CommandInterface $command, OptionCollection $options)
    {
        $this->command = $command;
        $this->options = $options;
        parent::__construct($message);
    }

    public function getOptions(): OptionCollection
    {
        return $this->options;
    }
}