<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/14 0019
 * Time: 15:30
 */

namespace Hail;
use Hail\Facades\DI;

/**
 * Class DITrait
 *
 * @package Hail
 * @property-read DI\Container $di
 * @property-read Cache\Embedded $embedded
 * @property-read Config $config
 * @property-read Loader $loader
 * @property-read AliasLoader $alias
 * @property-read Router $router
 * @property-read I18N\Gettext i18n
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
 * @property-read Browser $browser
 */
Trait DITrait
{
	/**
	 * @var DI\Container
	 */
	private static $_di;

	public function __get($name)
	{
		if (($v = self::di($name)) === null) {
			throw new Exception\InvalidState("Property $name Not Defined");
		}
		return $v;
	}

	final public static function di($name = null)
	{
		if (self::$_di === null) {
			self::$_di = DI::getInstance();
		}

		if ($name === null) {
			return self::$_di;
		}

		return self::$_di->get($name) ?? null;
	}
}