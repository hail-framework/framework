<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2016/10/14 0014
 * Time: 11:26
 */

namespace Hail\DI\Exception;

use Hail\Exception\InvalidArgument;
use Psr\Container\NotFoundException;

/**
 * Class NotFound
 *
 * @package Hail\DI\Exception
 */
class NotFound extends InvalidArgument implements NotFoundException
{

}