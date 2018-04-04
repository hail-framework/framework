<?php

/**
 * @param string ...$paths
 *
 * @return string
 */
function base_path(string ...$paths): string
{
    return absolute_path('@base', ...$paths);
}

/**
 * @param string ...$paths
 *
 * @return string
 */
function app_path(string ...$paths): string
{
    return absolute_path('@app', ...$paths);
}


/**
 * @param string ...$paths
 *
 * @return string
 */
function storage_path(string ...$paths): string
{
    return absolute_path('@storage', ...$paths);
}

/**
 * @param string ...$paths
 *
 * @return string
 */
function runtime_path(string ...$paths): string
{
    return absolute_path('@runtime', ...$paths);
}

/**
 * @param string ...$paths
 *
 * @return string
 */
function hail_path(string ...$paths): string
{
    return absolute_path('@hail', ...$paths);
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
    if ($root[0] === '@') {
        $absoluteRoot = root_path($root);
    } else {
        $root = rtrim(
            str_replace('\\', '/', $root),
            '/'
        );

        if (($absoluteRoot = realpath($root)) === false) {
            throw new InvalidArgumentException('ROOT path not exists: ' . $root);
        }
    }

    if ($paths === []) {
        return $absoluteRoot;
    }

    if (!isset($paths[1])) {
        $path = $paths[0];
    } else {
        $path = implode('/', $paths);
    }

    $path = $root . '/' . \trim(
            str_replace('\\', '/', $path),
            '/'
        );

    if (($absolutePath = realpath($path)) === false) {
        $parts = explode('/', $path);
        $absolutes = [];
        foreach ($parts as $part) {
            if ('.' === $part || '' === $part) {
                continue;
            }

            if ('..' === $part) {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        $absolutePath = implode(DIRECTORY_SEPARATOR, $absolutes);

        if (strpos($absolutePath, $absoluteRoot) !== 0) {
            throw new InvalidArgumentException('Path can not higher than ROOT.');
        }
    }

    return $absolutePath;
}

/**
 * @param string|array $key
 * @param string|null $path
 *
 * @return string
 */
function root_path($key, string $path = null): string
{
    static $paths = [];

    if ($path === null && is_string($key)) {
        if ($key[0] !== '@') {
            $key = "@$key";
        }

        return $paths[$key] ?? '';
    }

    if (!is_array($key)) {
        $array = [$key => $path];
    } else {
        $array = $key;
    }

    foreach ($array as $k => $v) {
        if (($absolute = realpath($v)) === false) {
            throw new InvalidArgumentException('Path not exists: ' . $path);
        }

        if ($k[0] !== '@') {
            $k = "@$k";
        }

        $paths[$k] = $absolute;
    }

    return '';
}

/**
 * Gets the value of an environment variable.
 *
 * @param  string $key
 *
 * @return mixed
 */
function env(string $key)
{
    $value = getenv($key);
    if ($value === false) {
        return null;
    }

    switch (strtolower($value)) {
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

    if (($len = strlen($value)) > 1 && $value[0] === '"' && $value[$len - 1] === '"') {
        return substr($value, 1, -1);
    }

    return $value;
}

/**
 * @param string $key
 *
 * @return mixed
 */
function config(string $key)
{
    if (class_exists('Config', false)) {
        return Config::get($key);
    }

    return (new \Hail\Config(root_path('@base')))->get($key);
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

    exit;
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