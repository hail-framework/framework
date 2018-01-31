<?php

namespace Hail\Template\Processor\Vue;


class Syntax
{
    private const TAG_QUOTE = '#@!QUOTE!@#';

    private const SUPPORT_SIGN = [
        '++', '--',
        '!==', '!=', '===', '==',
        '||', '&&',
        '!',
        '<', '>', '<=', '>=', '=>',
        '+', '-', '*', '%', '/',
    ];

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
        if (($result = self::getCache($expression)) !== null) {
            return $result;
        }

        if (self::hasSign($expression)) {
            return self::setCache(
                $expression,
                self::sign($expression)
            );
        }

        return self::line($expression);
    }

    private static function getCache(string &$expression): ?string
    {
        $expression = \trim($expression);

        if ($expression === '' || \is_numeric($expression)) {
            return $expression;
        }

        return self::$cache[$expression] ?? null;
    }

    private static function setCache(string $expression, string $result): string
    {
        return self::$cache[$expression] = $result;
    }


    private static function hasSign(string $expression): bool
    {
        static $replaceSign = null;

        if ($replaceSign === null) {
            $replaceSign = \array_combine(self::SUPPORT_SIGN,
                \array_fill(0, \count(self::SUPPORT_SIGN), '')
            );
        }

        return \strtr($expression, $replaceSign) !== $expression;
    }

    private static function sign(string $expression, int $i = 0): string
    {
        if (!isset(self::SUPPORT_SIGN[$i])) {
            $prefix = $suffix = '';

            if ($expression[0] === '(') {
                $prefix = '(';
                $expression = \substr($expression, 1);
            }

            if ($expression[-1] === ')') {
                $suffix = ')';
                $expression = \substr($expression, 0, -1);
            }

            return $prefix . self::line($expression) . $suffix;
        }

        $sign = self::SUPPORT_SIGN[$i++];

        if (\strpos($expression, $sign) === false) {
            return self::sign($expression, $i);
        }

        $parts = self::explode($sign, $expression);

        foreach ($parts as &$exp) {
            $exp = self::sign($exp, $i);
        }

        if ($sign === '+') {
            return '$this->jsPlus(' . \implode(',', $parts) . ')';
        }

        return \implode($sign, $parts);
    }

    /**
     * @param string $expression
     *
     * @return string
     */
    private static function line(string $expression): string
    {
        if (($result = self::getCache($expression)) !== null) {
            return $result;
        }

        if ($expression[0] === '\'' && $expression[-1] === '\'') {
            $result = self::string(\substr($expression, 1, -1));
        } else {
            $result = self::variable($expression);
        }

        return self::setCache($expression, $result);
    }

    private static function string(string $exp): string
    {
        return \var_export($exp, true);
    }

    private static function variable(string $expression): string
    {
        $parts = self::explode('.', $expression);

        $value = '';
        foreach ($parts as $part) {
            if ($value === '') {
                $value .= "\$this->wrap(\${$part})";
            } elseif ($part[-1] === ')') {
                [$fun, $args] = \explode('(', $part, 2);
                $args = \substr($args, 0, -1);
                if ($args !== '') {
                    $args = \implode(', ', self::explode(',', $args, 'self::line'));
                }

                $value .= "->{$fun}({$args})";
            } elseif ($part[-1] === ']') {
                [$arg, $property] = \explode('[', $part, 2);
                $property = self::line($property);
                $value .= "->{$arg}[$property]";
            } else {
                $value .= "->{$part}";
            }
        }

        return $value;
    }

    private static function explode(string $delimiter, string $string, callable $callable = null): array
    {
        $temp = '';
        $args = [];

        if ($replaceQuote = (\strpos($string, '\\\'') !== false)) {
            $string = \str_replace('\\\'', self::TAG_QUOTE, $string);
        }

        foreach (\explode($delimiter, $string) as $v) {
            $inQuote = \strpos($v, '\'') !== false &&
                \substr_count($v, '\'') % 2 === 1;

            if ($temp !== '') {
                $temp .= $delimiter . $v;

                if ($inQuote) {
                    $args[] = \trim($temp);
                    $temp = '';
                }
            } elseif ($inQuote) {
                $temp = $v;
            } else {
                $args[] = \trim($v);
            }
        }

        foreach ($args as &$arg) {
            if ($replaceQuote) {
                $arg = \str_replace(self::TAG_QUOTE, '\\\'', $arg);
            }

            if ($callable !== null) {
                $arg = $callable($arg);
            }
        }

        return $args;
    }
}