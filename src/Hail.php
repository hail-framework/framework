<?php

\defined('OPCACHE_INVALIDATE') || \define('OPCACHE_INVALIDATE', \function_exists('\opcache_invalidate'));

use Hail\{
    Facade\Facade,
    Container\Compiler,
    Container\Container,
    Debugger\Debugger
};
use Hail\Config\{
    Config, Path, Env
};

if (PHP_VERSION_ID < 70200 && (\ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING) !== 0) {
    \ini_set('mbstring.func_overload', '0');
}

if (\mb_internal_encoding() !== 'UTF-8') {
    \mb_internal_encoding('UTF-8');
}

/**
 * Class Framework
 *
 * @package Hail
 */
class Hail
{
    private static $defaultPaths = [
        'root' => '%s',
        'app' => '%s/app',
        'storage' => '%s/storage',
        'runtime' => '%s/storage/runtime',
    ];

    private static $_path;
    private static $_env;
    private static $_config;
    private static $_di;

    public static function setRoot(string $root = null): void
    {
        $path = self::$_path ?? self::path();

        foreach (self::$defaultPaths as $k => $v) {
            $path->base($k, \sprintf($v, $root));
        }
    }

    public static function path(): Path
    {
        if (self::$_path === null) {
            $path = Path::getInstance();
            $path->base('hail', \dirname(__DIR__));

            $root = \substr(__DIR__, 0,
                \strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor')
            );

            foreach (self::$defaultPaths as $k => $v) {
                $path->base($k, \sprintf($v, $root));
            }

            self::$_path = $path;
        }

        return self::$_path;
    }

    /**
     * @param string|null $key
     *
     * @return Env|string|bool|null
     */
    public static function env(string $key = null)
    {
        if (self::$_env === null) {
            $env = Env::getInstance();
            $path = self::$_path ?? self::path();
            $env->load(
                $path->root(Env::FILE)
            );
            self::$_env = $env;
        } else {
            $env = self::$_env;
        }

        if ($key === null) {
            return $env;
        }

        $value = $env->get($key);
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
    public static function config(string $key = null)
    {
        if (self::$_config === null) {
            $path = self::$_path ?? self::path();

            self::$_config = new Config(
                $path->root('config'),
                $path->runtime('config')
            );
        }

        if ($key === null) {
            return self::$_config;
        }

        return self::$_config->get($key);
    }

    /**
     * @return Container
     */
    public static function di()
    {
        if (self::$_di === null) {
            if (!\class_exists('\Container', false)) {
                $path = self::$_path ?? self::path();
                $file = $path->runtime('Container.php');

                $recompile = !\is_file($file);
                if (!$recompile) {
                    $recompile = \filemtime($file) < self::config()->modifyTime('container');
                }

                if ($recompile) {
                    $compiler = new Compiler(
                        self::config('container')
                    );

                    \file_put_contents($file, $compiler->compile());

                    if (OPCACHE_INVALIDATE) {
                        \opcache_invalidate($file, true);
                    }
                }

                require $file;
            }

            self::$_di = new \Container();
        }

        return self::$_di;
    }

    public static function set(string $name, $value)
    {
        self::di()->set($name, $value);
    }

    public static function get(string $name)
    {
        return self::di()->get($name);
    }

    public static function bootstrap(): Container
    {
        $container = self::$_di ?? self::di();
        $container->set('config', self::config());

        Facade::setContainer($container);
        $container->get('alias')->register();

        static::timezone(
            self::config('app.timezone')
        );

        Debugger::enable(
            self::env('PRODUCTION_MODE'),
            self::path()->storage('log')
        );

        return $container;
    }

    public static function compileContainer(Config $config): string
    {
        $file = \runtime_path('Container.php');

        if (
            !\is_file($file) ||
            \filemtime($file) < $config->getMTime('container')
        ) {
            $compiler = new Compiler(
                $config->get('container')
            );

            \file_put_contents($file, $compiler->compile());

            if (OPCACHE_INVALIDATE) {
                \opcache_invalidate($file, true);
            }
        }

        return $file;
    }

    public static function timezone(string $timezone)
    {
        if ($timezone !== \date_default_timezone_get()) {
            \date_default_timezone_set($timezone);
        }
    }
}