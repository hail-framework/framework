<?php

namespace Hail\Container\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * @inheritdoc
 */
class InvalidArgumentException extends \InvalidArgumentException implements ContainerExceptionInterface
{
}
