<?php

namespace Hail\Http\Client\Exception;

use Hail\Http\Client\Psr\ClientException;

/**
 * Base exception for transfer related exceptions.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
class TransferException extends \RuntimeException implements ClientException
{
}