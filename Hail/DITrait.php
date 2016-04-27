<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/14 0019
 * Time: 15:30
 */

namespace Hail;

/**
 * Class DITrait
 *
 * @package Hail
 * @property-read DI $di
 * @property-read Cache\Embedded $embedded
 * @property-read Config $config
 * @property-read Loader\PSR4 $loader
 * @property-read Loader\Alias $alias
 * @property-read Router $router
 * @property-read I18N\Gettext $gettext
 * @property-read Http\Request $request
 * @property-read Http\Response $response
 * @property-read Event\Emitter $event
 * @property-read Application $app
 * @property-read Output $output
 * @property-read Latte\Engine $template
 * @property-read DB\Medoo $db
 * @property-read Acl $acl
 * @property-read Session $session
 * @property-read Cookie $cookie
 * @property-read Cache $cache
 * @property-read DB\Cache $cdb
 * @property-read Utils\ObjectFactory $lib
 * @property-read Utils\ObjectFactory $model
 * @property-read Buzz\Browser $client
 */
Trait DITrait
{
	/**
	 * @var DI|null
	 */
	protected static $_di = null;

	public function __get($name)
	{
		if (($v = static::di($name)) === null) {
			throw new \RuntimeException("Property $name Not Defined");
		}
		return $v;
	}

	public static function di($name)
	{
		if (static::$_di === null) {
			static::$_di = \DI::instance();
		}

		return static::$_di[$name] ?? null;
	}
}