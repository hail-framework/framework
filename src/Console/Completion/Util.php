<?php

namespace Hail\Console\Completion;

class Util
{
    public static function qq($str)
    {
        return '"' . addcslashes($str, '"') . '"';
    }

    public static function q($str)
    {
        return "'" . addcslashes($str, "'") . "'";
    }

    public static function array_qq(array $array)
    {
        return array_map('self::qq', $array);
    }

    public static function array_q(array $array)
    {
        return array_map('self::q', $array);
    }

    public static function array_escape_space(array $array)
    {
        return array_map(function ($a) {
            return addcslashes($a, ' ');
        }, $array);
    }

    public static function array_indent(array $lines, $level = 1)
    {
        $space = str_repeat('  ', $level);

        return array_map(function ($line) use ($space) {
            return $space . $line;
        }, $lines);
    }
}
