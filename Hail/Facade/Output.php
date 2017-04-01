<?php
namespace Hail\Facade;

use Hail;
/**
 * Class Output
 *
 * @package Hail\Facade
 *
 * @method static object get(string $name)
 * @method static Hail\Output\Json json()
 * @method static Hail\Output\Jsonp jsonp()
 * @method static Hail\Output\File file()
 * @method static Hail\Output\Text text()
 * @method static Hail\Output\Template template()
 * @method static Hail\Output\Redirect redirect()
 */
class Output extends Facade
{
	protected static function instance()
	{
		return new Hail\Output();
	}
}