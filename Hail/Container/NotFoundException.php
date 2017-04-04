<?php

namespace Hail\Container;

use Exception;
use Psr\Container\NotFoundException as PsrNotFoundException;

/**
 * @inheritdoc
 */
class NotFoundException extends Exception implements PsrNotFoundException
{
    /**
     * @param string $name component name
     */
    public function __construct($name)
    {
        parent::__construct("undefined component: {$name}");
    }
}
