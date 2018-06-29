<?php
/*
 * This file is part of the CLIFramework package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console;

use Hail\Console\Exception\CommandClassNotFoundException;

/**
 * Class CommandLoader
 *
 * @package Hail\Console
 */
class CommandLoader
{
    /**
     * Load command class/subclass
     *
     * @param string $class
     *
     * @return string|null loaded class name
     * @throws CommandClassNotFoundException
     */
    public static function load(string $class): ?string
    {
        if (\is_subclass_of($class, Command::class, true)) {
            return $class;
        }

        if (\strrchr($class, 'C') !== 'Command') {
            return static::load($class . 'Command');
        }

        return null;
    }

    private static function clearSuffix(string $name): string
    {
        if (\strlen($name) > 7 && \strrchr($name, 'C') === 'Command') {
            $name = \substr($name, 0, -7);
        }

        return $name;
    }

    /**
     * Add all commands in a directory to parent command
     *
     * @param Command $parent object we want to load its commands/subcommands
     *
     * @return void
     * @throws CommandClassNotFoundException
     * @throws \ReflectionException
     */
    public static function autoload(Command $parent)
    {
        $reflector = new \ReflectionObject($parent);
        $dir = \dirname($reflector->getFileName()) . '/';

        /*
         * Commands to be autoloaded must located at specific directory.
         * If parent is Application, commands must be whthin App/Command/ directory.
         * If parent is another command named Foo or FooCommand, sub-commands must
         *      within App/Command/Foo/ directory, if App/Command/Foo/ directory
         *      not exists found in App/Command/Command/ directory.
         */
        if ($parent->isApplication()) {
            $subNamespace = 'Command';
        } else {
            $subNamespace = static::clearSuffix(
                $reflector->getShortName()
            );

            if (!\is_dir($dir . $subNamespace)) {
                $subNamespace = 'Command';
            }
        }

        $dir .= $subNamespace;
        if (!\is_dir($dir)) {
            return;
        }

        $classes = static::scanPhp($dir);
        $namespace = '\\' . $reflector->getNamespaceName() . '\\' . $subNamespace;

        foreach ($classes as $class) {
            $class = $namespace . '\\' . $class;

            $reflection = new \ReflectionClass($class);
            if ($reflection->isInstantiable()) {
                $parent->addCommand($class);
            }
        }
    }

    private static function scanPhp($path)
    {
        if (!\is_dir($path)) {
            return [];
        }

        $files = \scandir($path, SCANDIR_SORT_ASCENDING);

        $found = [];
        foreach ($files as $v) {
            if (\strrchr($v, '.') !== '.php') {
                continue;
            }

            $found[] = \substr($v, 0, -4);
        }

        return $found;
    }

    /**
     * Translate class name to command name
     *
     * This method is inverse of self::translate()
     *
     *     HelpCommand => help
     *     SuchALongCommand => such:a:long
     *
     * @param string $className class name.
     *
     * @return string translated command name.
     */
    public static function inverseTranslate(string $className): string
    {
        // remove the suffix 'Command', then lower case the first letter
        $className = \lcfirst(static::clearSuffix($className));

        return \strtolower(
            \preg_replace('/([A-Z])/', ':\1', $className)
        );
    }
}
