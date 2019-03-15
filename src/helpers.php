<?php

if (PHP_VERSION_ID < 70200 && (ini_get('mbstring.func_overload') & MB_OVERLOAD_STRING) !== 0) {
    ini_set('mbstring.func_overload', '0');
}

if (mb_internal_encoding() !== 'UTF-8') {
    mb_internal_encoding('UTF-8');
}

class_alias(Hail\Hail::class, Hail::class);

/**
 * @param string ...$paths
 *
 * @return string
 */
function root_path(string ...$paths): string
{
    return Hail::path('@root', ...$paths);
}

/**
 * @param string ...$paths
 *
 * @return string
 */
function app_path(string ...$paths): string
{
    return Hail::path('@app', ...$paths);
}


/**
 * @param string ...$paths
 *
 * @return string
 */
function storage_path(string ...$paths): string
{
    return Hail::path('@storage', ...$paths);
}

/**
 * @param string ...$paths
 *
 * @return string
 */
function runtime_path(string ...$paths): string
{
    return Hail::path('@runtime', ...$paths);
}

/**
 * @param string ...$paths
 *
 * @return string
 */
function hail_path(string ...$paths): string
{
    return Hail::path('@hail', ...$paths);
}

/**
 * @param string   $root
 * @param string ...$paths
 *
 * @return string
 * @throws InvalidArgumentException
 */
function absolute_path(string $root, string ...$paths): string
{
    return Hail::path($root, ...$paths);
}

/**
 * Gets the value of an environment variable.
 *
 * @param  string $key
 *
 * @return bool|string|null
 */
function env(string $key)
{
    return Hail::env($key);
}

/**
 * @param string $key
 *
 * @return mixed
 */
function config(string $key)
{
    return Hail::config($key);
}

/**
 * @param mixed ...$args
 *
 * @return mixed|null
 * @tracySkipLocation
 */
function dump(...$args)
{
    array_map('Hail\Debugger\Debugger::dump', $args);

    return $args[0] ?? null;
}

/**
 * @param mixed ...$args
 * @tracySkipLocation
 */
function dumpe(...$args)
{
    array_map('Hail\Debugger\Debugger::dump', $args);

    if (!\Hail\Debugger\Debugger::isProductionMode()) {
        exit;
    }
}

/**
 * Tracy\Debugger::barDump() shortcut.
 *
 * @param  mixed  $var     variable to dump
 * @param  string $title   optional title
 * @param  array  $options dumper options
 *
 * @return mixed
 * @tracySkipLocation
 */
function bdump($var, $title = null, array $options = null)
{
    \Hail\Debugger\Debugger::barDump($var, $title, $options);

    return $var;
}

/**
 * Change default timezone
 *
 * @param string $timezone
 */
function timezone(string $timezone = null): void
{
    Hail::timezone($timezone);
}