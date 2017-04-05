<?php

namespace Hail\Container\Exception;

use Psr\Container\NotFoundException as PsrNotFoundException;

/**
 * @inheritdoc
 */
class NotFoundException extends ContainerException implements PsrNotFoundException
{
    /**
     * @param string $name component name
     */
    public function __construct($name)
    {
        parent::__construct("Undefined component: {$name}");
    }
}
