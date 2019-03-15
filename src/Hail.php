<?php

namespace Hail;

/**
 * Class Hail
 *
 * @package Hail
 * @method static bool                             setRootPath(string $root = null)
 * @method static mixed                            path(string $name = null, string ...$paths)
 * @method static mixed                            env(string $key = null)
 * @method static mixed                            config(string $key = null)
 * @method static Framework                        set(string $name, $value)
 * @method static mixed                            get(string $name)
 * @method static mixed                            timezone(string $timezone = null)
 * @method static AliasLoader                      alias()
 * @method static Router                           router()
 * @method static I18N\I18N                        i18n()
 * @method static Event\EventManager               event()
 * @method static Application                      app()
 * @method static Template\Engine                  template()
 * @method static Database\Database                db()
 * @method static Auth\Manager                     auth()
 * @method static Session\Session                  session()
 * @method static Cache\Simple\CacheInterface      cache()
 * @method static Database\Cache\CachedDBInterface cdb()
 * @method static Http\Browser                     browser()
 * @method static Util\Arrays                      arrays()
 * @method static Crypto\Crypto                    crypto()
 * @method static Util\Generators                  generators()
 * @method static Util\Strings                     strings()
 * @method static Filesystem\MountManager          filesystem()
 * @method static Debugger\Debugger                debugger()
 * @method static Http\RequestHandler              dispatcher()
 * @method static Http\Request                     request()
 * @method static Http\Response                    response()
 * @method static Swoole\Http\Server               httpServer()
 * @method static Serialize\Serializer             serializer()
 * @method static Serialize\Json                   json()
 * @method static Serialize\Yaml                   yaml()
 * @method static \ServiceFactory                  service()
 * @method static \LibraryFactory                  lib()
 * @method static \ModelFactory                    model()
 *
 */
class Hail
{
    private static $hail;

    public static function __callStatic(string $name, array $arguments)
    {
        $hail = self::$hail ?? self::getInstance();

        return $hail->$name(...$arguments);
    }

    public static function getInstance()
    {
        if (self::$hail) {
            self::$hail = new Framework();
        }

        return self::$hail;
    }

}