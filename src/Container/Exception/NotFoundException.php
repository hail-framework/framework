<?php

namespace Hail\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * @inheritdoc
 */
class NotFoundException extends \RuntimeException implements NotFoundExceptionInterface
{
    /**
     * @param string $name component name
     */
    public function __construct($name)
    {
        parent::__construct("Undefined component: {$name}");
    }
}
