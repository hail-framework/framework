<?php

namespace Hail\Console\Exception;

use Exception;

/**
 * Class CommandClassNotFoundException
 *
 * @package Hail\Console\Exception
 */
class CommandClassNotFoundException extends Exception
{
    /**
     * CommandClassNotFoundException constructor.
     *
     * @param string $class
     */
    public function __construct(string $class)
    {
        parent::__construct("Command $class not found.");
    }
}
