<?php
namespace Hail\DI\Exception;

use Psr\Container\ContainerException as PsrContainerException;

/**
 * Class ContainerException
 *
 * @package Hail\DI\Exception
 * @author  Hao Feng <flyinghail@msn.com>
 */
class ContainerException extends \RuntimeException implements PsrContainerException
{

}