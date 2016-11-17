<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/7/5 0005
 * Time: 20:27
 */

namespace Hail\Facades;

use Hail;

/**
 * Class Application
 *
 * @package Hail\Facades
 *
 * @method static void run()
 * @method static Hail\Dispatcher getDispatcher(string $app)
 */
class Application extends Facade
{
	protected static $name = 'app';

	protected static function instance()
	{
		return new Hail\Application();
	}
}