<?php

namespace Hail\Container\Exception;

use Psr\Container\ContainerException as PsrContainerException;

/**
 * @inheritdoc
 */
class InvalidArgumentException extends \InvalidArgumentException implements PsrContainerException
{
}
