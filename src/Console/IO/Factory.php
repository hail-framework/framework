<?php

namespace Hail\Console\IO;


class Factory
{
    protected static $stty;
    protected static $console;

    public static function stty()
    {
        if (self::$stty === null) {
            if ('\\' === DIRECTORY_SEPARATOR) {
                return self::$stty = new NullStty();
            }

            return self::$stty = new UnixStty();
        }

        return self::$stty;
    }

    public static function console()
    {
        if (self::$console === null) {
            if (ReadlineConsole::isAvailable()) {
                return self::$console = new ReadlineConsole(self::stty());
            }

            return self::$console = new StandardConsole(self::stty());
        }

        return self::$console;
    }

}