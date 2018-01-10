<?php
namespace Hail\Console\ConsoleInfo;

class EnvConsoleInfo implements ConsoleInfoInterface
{
    public function getColumns()
    {
        return (int) getenv('COLUMNS');
    }

    public function getRows()
    {
        return (int) getenv('LINES');
    }

    public static function hasSupport()
    {
        return getenv('COLUMNS') && getenv('LINES');
    }
}
