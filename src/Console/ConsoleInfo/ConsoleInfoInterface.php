<?php
namespace Hail\Console\ConsoleInfo;

interface ConsoleInfoInterface
{
    public function getColumns();
    public function getRows();
    public static function hasSupport();
}
