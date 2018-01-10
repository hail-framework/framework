<?php

namespace Hail\Template\Processor\Tal;


use Hail\Template\Html\Token\Element;

final class Syntax
{
    private const PHP_OPERATORS = [
        ' LT ' => ' < ',
        ' GT ' => ' > ',
        ' LE ' => ' <= ',
        ' GE ' => ' >= ',
        ' EQ ' => ' == ',
        ' NE ' => ' != ',
        ' AND ' => ' && ',
        ' OR ' => ' || ',
    ];

    private const REPEAT_VARIABLE = [
        '/key' => '$__$1_key',
        '/index' => '$__$1_num - 1',
        '/number' => '$__$1_num',
        '/even' => '$__$1_num % 2 === 1',
        '/odd' => '$__$1_num % 2 === 0',
        '/start' => '$__$1_num === 1',
        '/end' => '$__$1_num === $__$1_count',
        '/length' => '$__$1_count',
    ];

    public static function resolve(string $expression): string
    {
        if (\strpos('|', $expression) !== false) {
            $parts = \explode('|', $expression);

            $result = [];
            foreach ($parts as $part) {
                $result[] = '(' . self::line($part) . ')';
            }

            return \implode(' ?: ', $result);
        }

        return self::line($expression);
    }

    public static function multiLine(string $expression, callable $resolve): array
    {
        $hasSemi = \strpos($expression, ';;') !== false;

        if (\strpos(';', $expression) !== false) {
            if ($hasSemi) {
                $expression = \str_replace(';;', '&@#semi#@&', $expression);
            }

            $parts = \explode(';', $expression);

            $result = [];
            foreach ($parts as $part) {
                if ($hasSemi) {
                    $part = \str_replace('&@#semi#@&', ';', $part);
                }

                $result[] = $resolve($part);
            }

            return $result;
        }

        if ($hasSemi) {
            $expression = \str_replace(';;', ';', $expression);
        }

        return [$resolve($expression)];
    }

    public static function lastKeyword(string $expression): array
    {
        $keyword = null;
        if (($pos = \strrpos('|', $expression)) !== false) {
            $keyword = \trim(\substr($expression, $pos + 1));
            $expression = \substr($expression, 0, $pos);
        }

        return [$keyword, \trim($expression)];
    }

    public static function isStructure(string $expression): array
    {
        if (\strpos($expression, 'structure ') === 0) {
            return [true, \substr($expression, 10)];
        }

        return [false, $expression];
    }

    public static function structure(bool $isStructure, string $expression): string
    {
        if ($isStructure) {
            return "echo $expression";
        }

        return "echo \htmlspecialchars($expression, ENT_HTML5)";
    }

    public static function resolveWithDefault(string $expression, Element $element): string
    {
        [$keyword, $exp] = self::lastKeyword($expression);

        if ($keyword === 'default') {
            $default = '';
            foreach ($element->getChildren() as $child) {
                $default .= (string) $child;
            }

            return '(' . self::resolve($exp) . ') ?: ' . \var_export($default, true);
        }

        if ($keyword !== null) {
            $exp = $expression;
        }

        return self::resolve($exp);
    }

    public static function phptales(string $expression): array
    {
        [$tag, $expression] = \explode(':', $expression, 2);

        return [\trim($tag), \trim($expression)];
    }

    public static function line(string $expression)
    {
        [$tag, $exp] = self::phptales($expression);

        switch ($tag) {
            case 'php': // php格式
                $exp = \strtr($exp, self::PHP_OPERATORS);

                // 里面的variable格式需要替换。不带$符号，且有数组形式
                if (\strpos($exp, '$') === false && \preg_match('/(\w+)\[/', $exp, $matches) === 1) {
                    $exp = \preg_replace('/(\w+)\[/', '$$1[', $exp);
                }

                return self::repeatVariable($exp);

            case 'not':
                return '!(' . self::line($exp) . ')';

            case 'string':
                $exp = \str_replace(['$$', '"'], ['\\$', '\\"'], $exp);

                return '"' . $exp . '"';

            case 'exists':
                return 'isset(' . self::line($exp) . ')';

            case 'true':
                return '!empty(' . self::line($exp) . ')';

            default:
                return self::variable($expression);
        }
    }

    public static function variable(string $expression): string
    {
        if (\strpos($expression, '$') === false) {
            $arr = \explode('/', $expression);
            $str = '$';

            foreach ($arr as $v) {
                if ($str === '$') {
                    $str .= $v . '[\'';
                } else {
                    $str .= $v . '\'][\'';
                }
            }

            return \substr($str, -2);
        }

        return self::repeatVariable($expression);
    }

    /**
     * some/result => item:
     *
     * repeat/item/key : returns the item's key if some/result is an associative resource (index otherwise)
     * repeat/item/index : returns the item index (0 to count-1)
     * repeat/item/number : returns the item number (1 to count)
     * repeat/item/even : returns true if item index is even
     * repeat/item/odd : returns true if item index is odd
     * repeat/item/start : returns true if item is the first one
     * repeat/item/end : returns true if item is the last one
     * repeat/item/length : returns the number of elements in some/result
     *
     * @param string $expression
     *
     * @return string
     */
    private static function repeatVariable(string $expression): string
    {
        if (\strpos($expression, 'repeat/') !== false) {
            foreach (self::REPEAT_VARIABLE as $k => $replace) {
                if (\strpos($expression, $k) > 8) {
                    $expression = \preg_replace("/repeat\/(\w+)\\{$k}/", $replace, $expression);
                }
            }
        }

        return $expression;
    }
}