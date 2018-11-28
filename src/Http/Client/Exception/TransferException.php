<?php

namespace Hail\Http\Client\Exception;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * Base exception for transfer related exceptions.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class TransferException extends \RuntimeException implements ClientExceptionInterface
{
}