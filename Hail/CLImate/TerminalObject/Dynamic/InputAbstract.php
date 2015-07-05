<?php

namespace Hail\CLImate\TerminalObject\Dynamic;

use Hail\CLImate\Util\Reader\ReaderInterface;
use Hail\CLImate\Util\Reader\Stdin;

abstract class InputAbstract extends DynamicTerminalObject
{
    /**
     * The prompt text
     *
     * @var string $prompt
     */
    protected $prompt;

    /**
     * An instance of ReaderInterface
     *
     * @var \Hail\CLImate\Util\Reader\ReaderInterface $reader
     */
    protected $reader;

    /**
     * Do it! Prompt the user for information!
     *
     * @return string
     */
    abstract public function prompt();

    /**
     * Format the prompt incorporating spacing and any acceptable options
     *
     * @return string
     */
    abstract protected function promptFormatted();
}
