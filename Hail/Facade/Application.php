<?php
namespace Hail\Facade;

/**
 * Class Application
 *
 * @package Hail\Facade
 *
 * @method static void run()
 * @method static \Hail\Dispatcher getDispatcher(string $app)
 */
class Application extends Facade
{
	protected static $name = 'app';
}