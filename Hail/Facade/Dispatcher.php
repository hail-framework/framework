<?php
namespace Hail\Facade;

use Psr\Http\{
	Message\ResponseInterface,
	Message\ServerRequestInterface
};

/**
 * Class Dispatcher
 *
 * @package Hail\Facade
 *
 * @method static ResponseInterface dispatch(ServerRequestInterface $request)
 */
class Dispatcher extends Facade
{
}