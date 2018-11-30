<?php

declare(strict_types=1);

namespace Hail\Http\Client\Exception;

/**
 * Thrown when an invalid argument is provided.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
