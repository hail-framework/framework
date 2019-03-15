<?php

namespace Hail;

\defined('OPCACHE_INVALIDATE') || \define('OPCACHE_INVALIDATE', \function_exists('\opcache_invalidate'));

use Hail\Config\{
    Path, Env, Config
};
use Hail\Container\{
    Compiler, Container
};

/**
 * Class Framework
 *
 * @package Hail
 *
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
class Framework
{
    protected static $defaultPaths = [
        'root' => '%s',
        'app' => '%s/app',
        'storage' => '%s/storage',
        'runtime' => '%s/storage/runtime',
    ];

    /**
     * @var Path
     */
    public $path;

    /**
     * @var Env
     */
    public $env;

    /**
     * @var Config
     */
    public $config;

    /**
     * @var Container
     */
    public $di;

    public function __construct()
    {
        $this->path = new Path(['hail' => \dirname(__DIR__)]);
        $this->setRootPath();

        $this->env = new Env([
            $this->path->root(Env::FILE),
        ]);

        $this->timezone();

        $this->config = new Config(
            $this->path->root('config'),
            $this->path->runtime('config')
        );

        if (!\class_exists('\Container', false)) {
            $file = $this->path->runtime('Container.php');

            $recompile = !\is_file($file);
            if (!$recompile) {
                $recompile = \filemtime($file) < $this->config->modifyTime('container');
            }

            if ($recompile) {
                $compiler = new Compiler(
                    $this->config->get('container')
                );

                \file_put_contents($file, $compiler->compile());

                if (OPCACHE_INVALIDATE) {
                    \opcache_invalidate($file, true);
                }
            }

            require $file;
        }

        $this->di = new \Container();

        $this->set('env', $this->env)
            ->set('path', $this->path)
            ->set('config', $this->config);

        $this->alias->register();

        $this->debugger->enable(
            $this->env->get('PRODUCTION_MODE'),
            $this->path->storage('log')
        );
    }

    public function setRootPath(string $root = null): bool
    {
        if ($root === null) {
            $root = \substr(__DIR__, 0,
                \strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor')
            );
        }

        if ($root) {
            foreach (self::$defaultPaths as $k => $v) {
                $this->path->base($k, \sprintf($v, $root));
            }
            return true;
        }

        return false;
    }

    public function path(string $name = null, string ...$paths)
    {
        if ($name === null) {
            return $this->path;
        }

        return $this->path->absolute($name, ...$paths);
    }

    /**
     * @param string|null $key
     *
     * @return Env|string|bool|null
     */
    public function env(string $key = null)
    {
        if ($key === null) {
            return $this->env;
        }

        $value = $this->env->get($key);
        if ($value === false) {
            return null;
        }

        switch (\strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (($len = \strlen($value)) > 1 && $value[0] === '"' && $value[$len - 1] === '"') {
            return \substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @param string|null $key
     *
     * @return Config|mixed
     */
    public function config(string $key = null)
    {
        if ($key === null) {
            return $this->config;
        }

        return $this->config->get($key);
    }

    public function __get(string $name)
    {
        return $this->get($name);
    }

    public function __call(string $name, $arguments)
    {
        return $this->get($name);
    }

    public function set(string $name, $value)
    {
        $this->di->set($name, $value);

        return $this;
    }

    public function get(string $name)
    {
        return $this->di->get($name);
    }

    public function timezone(string $timezone = null): self
    {
        if ($timezone === null) {
            $timezone = (string) $this->env('TIMEZONE');
        }

        if ($timezone && $timezone !== \date_default_timezone_get()) {
            \date_default_timezone_set($timezone);
        }

        return $this;
    }
}