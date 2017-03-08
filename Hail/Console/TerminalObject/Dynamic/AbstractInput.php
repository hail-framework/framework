<?php

namespace Hail\Console\TerminalObject\Dynamic;

use Hail\Console\Util\Reader\Stdin;

abstract class AbstractInput extends AbstractDynamic
{
    /**
     * The prompt text
     *
     * @var string $prompt
     */
    protected $prompt;

    /**
     * An instance of Stdin reader
     *
     * @var Stdin $reader
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
