<?php

namespace Hail\Util;

use Hail\Optimize\Optimize;
use Hail\Optimize\OptimizeTrait;

/**
 * This is the dotenv class.
 *
 * It's responsible for loading a `.env` file in the given directory and
 * setting the environment vars.
 */
class Env
{
    use OptimizeTrait;
    use SingletonTrait;

    protected const FILE = '.env';

    /**
     * Are we immutable?
     *
     * @var bool
     */
    protected static $immutable = true;

    /**
     * The list of environment variables declared inside the 'env' file.
     *
     * @var array
     */
    protected static $names = [];


    protected function init(): void
    {
        static::optimizeInstance(
            new Optimize([
                'adapter' => 'auto',
                'delay' => 5,
            ])
        );
    }

    /**
     * Set immutable value.
     *
     * @param bool $immutable
     */
    public static function setImmutable(bool $immutable = false): void
    {
        static::$immutable = $immutable;
    }

    /**
     * Get immutable value.
     *
     * @return bool
     */
    public static function isImmutable(): bool
    {
        return static::$immutable;
    }

    /**
     * Get the list of environment variables declared inside the 'env' file.
     *
     * @return array
     */
    public static function getNames(): array
    {
        return static::$names;
    }

    /**
     * Load `.env` file in given directory.
     *
     * @param string $path
     */
    public static function load(string $path): void
    {
        $filePath = \absolute_path($path, static::FILE);
        if (!\is_readable($filePath) || !\is_file($filePath)) {
            return;
        }

        $array = self::optimizeGet($path, $filePath);

        if ($array === false) {
            $array = \parse_ini_file($filePath, false, INI_SCANNER_RAW);
        }

        foreach ($array as $name => $value) {
            static::set($name, $value);
        }

        self::optimizeSet($path, $array, $filePath);
    }

    /**
     * Search the different places for environment variables and return first value found.
     *
     * @param string $name
     *
     * @return string|null
     */
    public static function get(string $name): ?string
    {
        if (isset($_ENV[$name])) {
            return $_ENV[$name];
        }

        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        }

        $value = \getenv($name);

        return $value === false ? null : $value; // switch getenv default to null
    }

    /**
     * Set an environment variable.
     *
     * @param string $name
     * @param string $value
     *
     * @return void
     */
    protected static function set(string $name, string $value): void
    {
        $name = \trim($name);

        if (
            (isset($name[0]) && $name[0] === '#') ||
            (static::$immutable && static::get($name) !== null)
        ) {
            return;
        }

        static::$names[] = $name;
        $value = \trim($value);

        // If PHP is running as an Apache module and an existing
        // Apache environment variable exists, overwrite it
        if (
            \function_exists('\\apache_setenv') &&
            \function_exists('\\apache_getenv') &&
            \apache_getenv($name) !== false
        ) {
            \apache_setenv($name, $value);
        }

        if (\function_exists('\\putenv')) {
            \putenv("$name=$value");
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }

    /**
     * Clear an environment variable.
     *
     * @param string $name
     *
     * @return void
     */
    public static function clear($name): void
    {
        if (static::$immutable) {
            return;
        }

        if (\function_exists('\\putenv')) {
            \putenv($name);
        }

        unset($_ENV[$name], $_SERVER[$name]);
    }
}