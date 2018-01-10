<?php
namespace Hail\Console\ConsoleInfo;

use Hail\Console\ConsoleInfo\EnvConsoleInfo;
use Hail\Console\ConsoleInfo\TputConsoleInfo;

class ConsoleInfoFactory
{
    public static function create()
    {
        if (EnvConsoleInfo::hasSupport()) {
            return new EnvConsoleInfo;
        }

        if (TputConsoleInfo::hasSupport()) {
            return new TputConsoleInfo;
        }
    }
}
