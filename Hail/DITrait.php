<?php

namespace Hail;

use Hail\Facade\DI;

/**
 * Class DITrait
 *
 * @package Hail
 * @property-read Container\Container          $di
 * @property-read Config                       $config
 * @property-read AliasLoader                  $alias
 * @property-read Router                       $router
 * @property-read I18N\I18N                    i18n
 * @property-read Http\ServerRequest           $request
 * @property-read Http\Response                $response
 * @property-read Event\EventManager           $event
 * @property-read Application                  $app
 * @property-read Output                       $output
 * @property-read Latte\Engine                 $template
 * @property-read Database\Database            $db
 * @property-read Acl                          $acl
 * @property-read Session                      $session
 * @property-read Cookie                       $cookie
 * @property-read Cache\Simple\CacheInterface  $cache
 * @property-read Cache\CacheItemPoolInterface $cachePool
 * @property-read Database\Cache               $cdb
 * @property-read Browser                      $browser
 * @property-read Util\Arrays                  $arrays
 * @property-read Util\Crypto                  $crypto
 * @property-read Util\Generators              $generators
 * @property-read Util\Json                    $json
 * @property-read Util\Serialize               $serialize
 * @property-read Util\Strings                 $strings
 * @property-read Filesystem\MountManager      $filesystem
 * @property-read \LibraryFactory              $lib
 * @property-read \ModelFactory                $model
 * @property-read Tracy\Debugger               $debugger
 * @property-read Http\Dispatcher              $dispatcher
 */
Trait DITrait
{
    public function __get(string $name)
    {
        return $this->$name = DI::get($name);
    }

    final public static function di(string $name = null)
    {
        if ($name === null) {
            return DI::getInstance();
        }

        return DI::get($name);
    }
}
