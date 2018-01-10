<?php

namespace Hail;

use Hail\{
    Facade\Facade,
    Container\Compiler,
    Container\Container,
    Debugger\Debugger,
    Util\Env
};

/**
 * Class Framework
 *
 * @package Hail
 */
class Framework
{
    public static function bootstrap($basePath): Container
    {
        \root_path([
            '@hail' => \dirname(__DIR__),
            '@base' => $basePath,
            '@app' => $basePath . '/app',
            '@storage' => $basePath . '/storage',
            '@runtime' => $basePath . '/storage/runtime',
        ]);

        if (PHP_VERSION_ID < 70200) {
            if ((\ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING) !== 0) {
                \ini_set('mbstring.func_overload', '0');
            }
        }

        if (\mb_internal_encoding() !== 'UTF-8') {
            \mb_internal_encoding('UTF-8');
        }

        Env::load($basePath);

        $config = new Config($basePath);

        if (!\class_exists('\Container', false)) {
            require self::compileContainer($config);
        }

        $container = new \Container();
        $container->set('config', $config);

        Facade::setContainer($container);
        $container->get('alias')->register();

        static::timezone(
            $config->get('app.timezone')
        );

        Debugger::enable(
            \env('PRODUCTION_MODE'),
            \storage_path('log')
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

            if (\function_exists('\opcache_invalidate')) {
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