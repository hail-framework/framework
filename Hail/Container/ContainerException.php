<?php

namespace Hail\Container;

use Exception;
use Psr\Container\ContainerException as PsrContainerException;

/**
 * @inheritdoc
 */
class ContainerException extends Exception implements PsrContainerException
{
}
