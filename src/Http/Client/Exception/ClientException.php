<?php

declare(strict_types=1);

namespace Hail\Http\Client\Exception;

use Psr\Http\Client\ClientExceptionInterface as PsrClientException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ClientException extends \RuntimeException implements ExceptionInterface, PsrClientException
{
}
