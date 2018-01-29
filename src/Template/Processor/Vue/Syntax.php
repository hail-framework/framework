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

        if (\is_numeric($expression)) {
            return $expression;
        }

        if ($expression[0] === '!') { // ! operator application
            return '!(' . static::parse(\substr($expression, 1)) . ')';
        }

        if ($expression[0] === '\'' && $expression[-1] === '\'') {
            return static::string(\substr($expression, 1, -1));
        }

        $parts = \explode('.', $expression);

        return static::variable($parts);
    }

    protected static function string(string $exp): string
    {
        return \var_export($exp, true);
    }

    protected static function variable(array $parts): string
    {
        $value = '';
        foreach ($parts as $part) {
            if ($value === '') {
                $value .= "\$this->wrap(\${$part})";
            } else {
                if (\preg_match('/^(\w+)\((.*)\)$/', $part, $matches)) {
                    $args = '';
                    if (!empty($matches[2])) {
                        $args = static::explode(',', $matches[2]);
                    }

                    $value .= "->{$matches[1]}({$args})";
                } else {
                    $value .= "->{$part}";
                }
            }
        }

        return $value;
    }

    protected static function explode(string $delimiter, string $string): string
    {
        $temp = '';
        $args = [];
        foreach (\explode($delimiter, $string) as $v) {
            if ($temp !== '') {
                if ($v[-1] === '\'') {
                    $args[] = $temp . $delimiter . $v;
                    $temp = '';
                } else {
                    $temp .= $v;
                }
            } elseif ($v[0] === '\'' && !(isset($v[1]) && $v[-1] === '\'')) {
                $temp = $v;
            } else {
                $args[] = $v;
            }
        }
        $args = \array_map('self::parse', $args);

        return \implode($delimiter, $args);
    }
}