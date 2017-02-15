<?php
namespace Hail\Container\Exception;

use Psr\Container\ContainerException as PsrContainerException;

/**
 * Class ContainerException
 *
 * @package Hail\Container\Exception
 * @author  Hao Feng <flyinghail@msn.com>
 */
class ContainerException extends \RuntimeException implements PsrContainerException
{

}