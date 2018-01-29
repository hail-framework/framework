<?php

namespace Hail\Template\Processor\Vue;


class Syntax
{
    /**
     * @var string[] Indexed by expression string
     */
    private static $cache;

    /**
     * @param string $expression
     *
     * @return string
     */
    public static function parse(string $expression): string
    {
        $expression = \trim($expression);
        if (isset(static::$cache[$expression])) {
            return static::$cache[$expression];
        }

        $result = static::jsParse($expression);

        return static::$cache[$expression] = $result;
    }

    /**
     * @param string $expression
     *
     * @return string
     */
    protected static function jsParse(string $expression): string
    {
        if ($expression === '') {
            return '';
        }

        if ($expression[0] === '!') { // ! operator application
            return '!(' . static::jsParse(\substr($expression, 1)) . ')';
        }

        if ($expression[0] === '\'') {
            return \var_export(\substr($expression, 1, -1), true);
        }

        $parts = \explode('.', $expression);

        return static::variableAccess($parts);
    }

    protected static function variableAccess(array $parts): string
    {
        $value = '';
        foreach ($parts as $key) {
            if ($value === '') {
                $value .= "\$this->wrap(\${$key})";
            } else {
                $value .= "->{$key}";
            }
        }

        return $value;
    }
}