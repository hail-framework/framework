<?php
namespace Hail\DI\Exception;

use Hail\Exception\InvalidArgument;
use Psr\Container\NotFoundException;

/**
 * Class NotFound
 *
 * @package Hail\DI\Exception
 * @author  Hao Feng <flyinghail@msn.com>
 */
class NotFound extends InvalidArgument implements NotFoundException
{

}