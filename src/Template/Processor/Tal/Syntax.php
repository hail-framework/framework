<?php

namespace Hail\Template\Processor\Tal;


use Hail\Template\Tokenizer\Token\Element;

final class Syntax
{
    private const SEMI = '&@#semi#@&';

    private const PHP_OPERATORS = [
        'LT' => '<',
        'GT' => '>',
        'LE' => '<=',
        'GE' => '>=',
        'EQ' => '==',
        'NE' => '!=',
        'AND' => '&&',
        'OR' => '||',
        'NOT' => '!',
    ];

    private const REPEAT_VARIABLE = [
        'key' => '$__{$item}_key',
        'index' => '$__{$item}_num - 1',
        'number' => '$__{$item}_num',
        'even' => '$__{$item}_num % 2 === 1',
        'odd' => '$__{$item}_num % 2 === 0',
        'start' => '$__{$item}_num === 1',
        'end' => '$__{$item}_num === $__{$item}_count',
        'length' => '$__{$item}_count',
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
        if ($hasSemi) {
            $expression = \str_replace(';;', self::SEMI, $expression);
        }

        if (\strpos(';', $expression) !== false) {
            $parts = \explode(';', $expression);

            $result = [];
            foreach ($parts as $part) {
                if ($hasSemi) {
                    $part = \str_replace(self::SEMI, ';', $part);
                }

                $result[] = $resolve($part);
            }

            return $result;
        }

        if ($hasSemi) {
            $expression = \str_replace(self::SEMI, ';', $expression);
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
        $expression = \trim($expression);

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
        $parts = \explode(':', $expression, 2);
        if (isset($parts[1])) {
            $tag = \trim($parts[0]);
            $expression = \trim($parts[1]);
        } else {
            $tag = null;
            $expression = \trim($parts[0]);
        }

        return [$tag, $expression];
    }

    public static function line(string $expression)
    {
        [$tag, $exp] = self::phptales($expression);

        switch ($tag) {
            case 'php': // php格式
                return self::phpLine($exp);

            case 'not':
                return '!(' . self::line($exp) . ')';

            case 'string':
                return self::string($exp);

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
        $expression = \trim($expression);

        if (!\preg_match('/[a-zA-Z]/', $expression)) {
            return $expression;
        }

        if ($expression[0] === '$') {
            return $expression;
        }

        if (($str = self::repeatVariable($expression)) !== null) {
            return $str;
        }

        if (\strpos($expression, '/') !== false) {
            $arr = \explode('/', $expression);
            $str = '$';

            foreach ($arr as $v) {
                if ($str === '$') {
                    $str .= $v;
                } elseif ($v === (string) ((int) $v)) {
                    $str = '[' . $v . ']';
                } else {
                    $str .= '[\'' . $v . '\']';
                }
            }

            return $str;
        }

        if (\strpos($expression, '.') !== false) {
            $arr = \explode('.', $expression);

            $str = '';
            foreach ($arr as $v) {
                if ($str === '') {
                    $str .= '$this->wrap($' . self::variable($v) . ')';
                    continue;
                }

                if (($fn = self::variableBrackets($v)) !== null) {
                    $v = $fn;
                }

                $str .= '->' . $v;
            }

            return $str;
        }

        if (($fn = self::variableBrackets($expression)) !== null) {
            return $fn;
        }

        if (!\preg_match('/^[\w\\\]+::\$?\w+$/', $expression)) {
            return $expression;
        }

        return '$' . $expression;
    }

    private static function variableBrackets(string $expression): ?string
    {
        $expression = \trim($expression);
        if ($expression[0] === '(' && $expression[-1] === ')') {
            return '(' . self::phpLine(\substr($expression, 1, -1)) . ')';
        }

        if (!\preg_match('/^([\w\\\]+)(?:::(\w*))\((.*)\)$/', $expression,$matches)) {
            return null;
        }

        $fn = $matches[1];
        if (empty($matches[2])) {
            $fn .= '::' . $matches[2];
        }

        if (empty($matches[3])) {
            return $fn . '()';
        }

        $args = [];
        $temp = '';
        $inStr = false;

        $chars = \preg_split('//u', $matches[3]);
        foreach ($chars as $char) {
            switch ($char) {
                case '\'':
                    if ($inStr) {
                        if ($temp[-1] === '\\') {
                            $temp .= $char;
                        } else {
                            $args[] = self::string($temp);
                            $temp = '';
                            $inStr = false;
                        }
                    } else {
                        $inStr = true;

                        if ($temp !== '') {
                            throw new \LogicException('TAL function struct error');
                        }
                    }
                    break;

                case ',':
                    if ($inStr) {
                        $temp .= $char;
                    } elseif ($temp !== '') {
                        $args[] = self::variable($temp);
                        $temp = '';
                    }
                    break;

                default:
                    if ($temp !== '' || $char !== ' ') {
                        $temp .= $char;
                    }
            }
        }

        if ($temp !== '') {
            $args[] = self::variable($temp);
        }

        return $matches[1] . '(' . \implode(',', $args) . ')';
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
     * @return string|null
     */
    private static function repeatVariable(string $expression): ?string
    {
        if (\strpos($expression, 'repeat/') !== 0) {
            return null;
        }

        $parts = \explode('/', $expression, 3);
        if (!isset($parts[2], self::REPEAT_VARIABLE[$parts[2]])) {
            return null;
        }

        return \str_replace('{$item}', $parts[1], self::REPEAT_VARIABLE[$parts[2]]);
    }

    /**
     * string:
     *
     * foo $bar baz : "foo $bar baz"
     * foo $$bar baz : "foo \$bar baz"
     * foo ${bar} baz : "foo " . $bar . " baz"
     * foo ${bar/a} baz : "foo " . $bar['a'] . " baz"
     * foo ${bar.a} baz : "foo " . $bar->a . " baz"
     * foo ${bar.a baz : "foo \${bar.a baz"
     *
     * @param string $expression
     *
     * @return string
     */
    public static function string(string $expression): string
    {
        if ($expression === '') {
            return '\'\'';
        }

        $return = '"';
        $string = $expression;
        while (($pos = \strpos($string, '$')) !== false) {
            $return .= \substr($string, 0, $pos);
            $string = \substr($string, $pos + 1);

            switch ($string[0]) {
                case '$':
                    $return .= '\\$';
                    $string = \substr($string, 1);
                    break;

                case '{':
                    if (($end = \strpos($string, '}')) === false) {
                        $return .= '\\$';
                    } else {
                        $return .= '" .' . self::line(\substr($string, 1, $end - 1)) . ' . "';
                        $string = \substr($string, $end + 1);
                    }
                    break;

                default:
                    $return .= '$';
                    $string = \substr($string, 1);
                    break;
            }
        }

        $return .= $string . '"';

        return $return;
    }

    private static function phpLine(string $expression)
    {
        $chars = \preg_split('//u', $expression);

        $parts = [];
        $temp = '';
        $inFun = 0;
        $inStr = false;
        foreach ($chars as $char) {
            switch ($char) {
                case ' ':
                    if ($inStr || $inFun > 0) {
                        $temp .= $char;
                    } elseif ($temp !== '') {
                        $parts[] = self::PHP_OPERATORS[$temp] ?? self::variable($temp);
                        $temp = '';
                    }
                    break;

                case '\'':
                    if ($inFun > 0) {
                        $temp .= $char;
                    } elseif ($inStr) {
                        if ($temp[-1] === '\\') {
                            $temp .= $char;
                        } else {
                            $parts[] = self::string($temp);
                            $temp = '';
                            $inStr = false;
                        }
                    } else {
                        $inStr = true;

                        if ($temp !== '') {
                            throw new \LogicException('TAL function struct error');
                        }
                    }
                    break;

                case '(':
                    if (!$inStr) {
                        ++$inFun;
                    }
                    $temp .= $char;
                    break;

                case ')':
                    $temp .= $char;
                    if (!$inStr && $inFun > 0 && --$inFun === 0) {
                        $parts[] = self::variableBrackets($temp);
                        $temp = '';
                    }
                    break;

                default:
                    if ($temp !== '') {
                        $temp .= $char;
                    }
            }
        }

        if ($temp !== '') {
            $parts[] = self::variable($temp);
        }

        return \implode(' ', $parts);
    }
}