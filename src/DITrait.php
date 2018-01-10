<?php

namespace Hail;

use Hail\Facade\DI;

/**
 * Class DITrait
 *
 * @package Hail
 * @property-read Container\Container              $di
 * @property-read Config                           $config
 * @property-read AliasLoader                      $alias
 * @property-read Router                           $router
 * @property-read I18N\I18N                        $i18n
 * @property-read Event\EventManager               $event
 * @property-read Application                      $app
 * @property-read Template\Engine                  $template
 * @property-read Database\Database                $db
 * @property-read Auth\Manager                     $auth
 * @property-read Session\Session                  $session
 * @property-read Cache\Simple\CacheInterface      $cache
 * @property-read Database\Cache\CachedDBInterface $cdb
 * @property-read Client                           $browser
 * @property-read Util\Arrays                      $arrays
 * @property-read Util\Crypto                      $crypto
 * @property-read Util\Generators                  $generators
 * @property-read Util\Json                        $json
 * @property-read Util\Serialize                   $serialize
 * @property-read Util\Strings                     $strings
 * @property-read Filesystem\MountManager          $filesystem
 * @property-read \ServiceFactory                  $service
 * @property-read \LibraryFactory                  $lib
 * @property-read \ModelFactory                    $model
 * @property-read Debugger\Debugger                $debugger
 * @property-read Http\RequestHandler              $dispatcher
 * @property-read Http\Request                     $request
 * @property-read Http\Response                    $response
 * @property-read Swoole\Http\Server               $httpServer
 */
Trait DITrait
{
    final public function __get(string $name)
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
