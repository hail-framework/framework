<?php

namespace Hail\Console;


class ValueType
{
    protected static $parsed;

    protected static function type(string $type)
    {
        $type = strtolower($type);
        switch ($type) {
            case 'bool':
                return 'boolean';

            case 'datetime':
                return 'dateTime';

            default:
                return $type;
        }
    }

    public static function test(string $type, $value, $option = null): ?bool
    {
        $fn = self::type($type) . 'Test';

        if (!method_exists(self::class, $fn)) {
            return null;
        }

        if ($option === null) {
            return self::$fn($value);
        }

        return self::$fn($value, $option);
    }

    public static function parse()
    {
        $return = self::$parsed;
        self::$parsed = null;

        if ($return instanceof \Closure) {
            return $return();
        }

        return $return;
    }

    protected static function booleanTest($value): bool
    {
        if (is_string($value)) {
            $value = strtolower($value);

            if ($value === '1' || $value === 'true') {
                self::$parsed = true;

                return true;
            }

            if ($value === '0' || $value === 'false') {
                self::$parsed = false;

                return true;
            }
        } elseif (is_bool($value)) {
            self::$parsed = $value;

            return true;
        }

        return false;
    }

    protected static function dateTimeTest($value, $format = \DateTime::ATOM): bool
    {
        $time = \DateTime::createFromFormat($format, $value);

        if ($time !== false) {
            self::$parsed = $time;

            return true;
        }

        return false;
    }

    /**
     * format: YYYY[MM[DD[HH[II[SS]]]]]
     *
     * @param $value
     *
     * @return bool
     */
    protected static function dateTest($value): bool
    {
        if (!preg_match('/^\d{4,14}$/', $value)) {
            return false;
        }

        // what we need to append to the date according to the possible date string lengths
        $dateLenToAppend = [
            14 => '',
            12 => '00',
            10 => '0000',
            8 => '000000',
            6 => '01000000',
            4 => '0101000000',
        ];

        $len = strlen($value);

        if (!isset($dateLenToAppend[$len])) {
            return false;
        }

        $dateTime = \DateTime::createFromFormat('YmdHis', $value . $dateLenToAppend[$len]);

        if ($dateTime === false) {
            return false;
        }

        return self::$parsed = $dateTime;
    }

    protected static function dirTest($value): bool
    {
        return self::fileInfo(is_dir($value), $value);
    }

    protected static function fileTest($value): bool
    {
        return self::fileInfo(is_file($value), $value);
    }

    protected static function pathTest($value): bool
    {
        return self::fileInfo(file_exists($value), $value);
    }

    protected static function fileInfo($check, $value): bool
    {
        if ($check) {
            self::$parsed = function () use ($value) {
                return new \SplFileInfo($value);
            };

            return true;
        }

        return false;
    }

    protected static function urlTest($value): bool
    {
        return self::filter($value, FILTER_VALIDATE_URL);
    }

    protected static function emailTest($value): bool
    {
        return self::filter($value, FILTER_VALIDATE_EMAIL);
    }

    protected static function ipTest($value): bool
    {
        return self::filter($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
    }

    protected static function ipv4Test($value): bool
    {
        return self::filter($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    protected static function ipv6Test($value): bool
    {
        return self::filter($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    protected static function filter($value, $filter, $options = null): bool
    {
        $value = (string) $value;

        if ($options === null) {
            $check = filter_var($value, $filter);
        } else {
            $check = filter_var($value, $filter, $options);
        }


        if ($check !== false) {
            self::$parsed = $value;

            return true;
        }

        return false;
    }

    protected static function intTest($value): bool
    {
        $int = (int) $value;

        if (((string) $int) === ((string) $value)) {
            self::$parsed = $int;

            return true;
        }

        return false;
    }

    protected static function numberTest($value): bool
    {
        if (is_numeric($value)) {
            self::$parsed = $value + 0;

            return true;
        }

        return false;
    }

    protected static function regexTest($value, $regex): bool
    {
        $value = (string) $value;

        if (preg_match($regex, $value) !== 0) {
            self::$parsed = $value;

            return true;
        }

        return false;
    }

    protected static function stringTest($value): bool
    {
        if (is_string($value)) {
            self::$parsed = $value;

            return true;
        }

        return false;
    }
}
