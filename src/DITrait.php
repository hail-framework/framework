<?php

namespace Hail;

/**
 * Class DITrait
 *
 * @package Hail
 * @property-read Config\Path                      $path
 * @property-read Config\Env                       $env
 * @property-read Config\Config                    $config
 * @property-read Container\Container              $di
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
 * @property-read Http\Browser                     $browser
 * @property-read Util\Arrays                      $arrays
 * @property-read Crypto\Crypto                    $crypto
 * @property-read Util\Generators                  $generators
 * @property-read Util\Strings                     $strings
 * @property-read Filesystem\MountManager          $filesystem
 * @property-read Debugger\Debugger                $debugger
 * @property-read Http\RequestHandler              $dispatcher
 * @property-read Http\Request                     $request
 * @property-read Http\Response                    $response
 * @property-read Swoole\Http\Server               $httpServer
 * @property-read Serialize\Serializer             $serializer
 * @property-read Serialize\Json                   $json
 * @property-read Serialize\Yaml                   $yaml
 * @property-read \ServiceFactory                  $service
 * @property-read \LibraryFactory                  $lib
 * @property-read \ModelFactory                    $model
 */
Trait DITrait
{
    final public function __get(string $name)
    {
        return $this->$name = Hail::get($name);
    }
}
