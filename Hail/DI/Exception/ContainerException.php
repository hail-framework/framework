<?php
namespace Hail\DI\Exception;

use Hail\Exception\InvalidStateException;
use Psr\Container\ContainerException as PsrContainerException;

/**
 * Class ContainerException
 *
 * @package Hail\DI\Exception
 * @author  Hao Feng <flyinghail@msn.com>
 */
class ContainerException extends InvalidStateException implements PsrContainerException
{

}