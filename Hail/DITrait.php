<?php

namespace Hail;

use Hail\DI\Container;
use Hail\Facades\DI;

/**
 * Class DITrait
 *
 * @package Hail
 * @property-read Container $di
 * @property-read Config $config
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
 * @property-read SimpleCache\CacheInterface $cache
 * @property-read Cache\CacheItemPoolInterface $cachePool
 * @property-read DB\Cache $cdb
 * @property-read Browser $browser
 * @property-read Util\Arrays $arrays
 * @property-read Util\Crypto $crypto
 * @property-read Util\Generators $generators
 * @property-read Util\Json $json
 * @property-read Util\Serialize $serialize
 * @property-read Util\Strings $strings
 * @property-read Filesystem\MountManager $filesystem
 * @property-read \LibraryFactory $lib
 * @property-read \ModelFactory $model
 */
Trait DITrait
{
	/**
	 * @var Container
	 */
	private static $_di;

	public function __get($name)
	{
		return $this->$name = self::$_di->get($name);
	}

	final public static function initDI()
	{
		self::$_di = DI::getInstance();
	}

	final public static function di($name = null)
	{
		if ($name === null) {
			return self::$_di;
		}

		return self::$_di->get($name);
	}
}

DITrait::initDI();