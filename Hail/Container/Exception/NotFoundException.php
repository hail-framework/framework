<?php
namespace Hail\Container\Exception;

use Psr\Container\NotFoundException as PsrNotFoundException;

/**
 * Class NotFound
 *
 * @package Hail\Container\Exception
 * @author  Hao Feng <flyinghail@msn.com>
 */
class NotFoundException extends ContainerException implements PsrNotFoundException
{

}