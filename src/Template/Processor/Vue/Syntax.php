<?php

namespace Hail\Template\Processor\Vue;

const T_OPEN_PARENTHESIS = 'OPEN_PARENTHESIS',
T_CLOSE_PARENTHESIS = 'CLOSE_PARENTHESIS';

/**
 * Class Syntax
 * Simple JS syntax parser
 *
 * @package Hail\Template\Processor\Vue
 */
class Syntax
{
    private const TOKEN = [
        '(', ')', '[', ']', '?', ':',
        '+', '-', '*', '%', '/',
        '^',
        '<', '>', '<=', '>=', '=>',
        '!', '||', '&&',
        '|', '&',
        '!=', '!==',
        '==', '===',
        '++', '--',
    ];

    public static function variable(string $expression): string
    {
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

        return '$' . $expression;
    }

    public static function line(string $expression): string
    {
        $chars = \preg_split('//u', $expression);

        $tree = self::parse($chars);

        return '';
    }

    /**
     * @param array $chars
     *
     * @return array
     *
     * @throws \LogicException when syntax error
     */
    private static function parse(array $chars): array
    {
        $tree = $children = [];
        $temp = '';
        $inString = false;
        $childrenLv = 0;
        $functionLv = 0;

        foreach ($chars as $char) {
            switch ($char) {
                case '(':
                    if ($inString) {
                        $temp .= $char;
                    } elseif ($functionLv > 0) {
                        $children[] = $char;
                        ++$functionLv;
                    } elseif ($childrenLv > 0) {
                        $children[] = $char;
                        ++$childrenLv;
                    } elseif ($temp !== '') {
                        $tree[] = [T_FUNCTION, $temp];
                        $tree[] = [T_OPEN_PARENTHESIS, $char];
                        $temp = '';

                        ++$functionLv;
                    } else {
                        $tree[] = [T_OPEN_PARENTHESIS, $char];
                        ++$childrenLv;
                    }
                    break;

                case ')':
                    if ($inString) {
                        $temp .= $char;
                    } elseif ($functionLv > 0) {
                        if (--$functionLv === 0) {
                            $tree[] = self::parseFunArgs($children);
                            $tree[] = [T_CLOSE_PARENTHESIS, $char];
                        } else {
                            $children[] = $char;
                        }
                    } elseif ($childrenLv > 0) {
                        if (--$childrenLv === 0) {
                            $tree[] = self::parse($children);
                            $tree[] = [T_CLOSE_PARENTHESIS, $char];
                        } else {
                            $children[] = $char;
                        }
                    } else {
                        throw new \LogicException('Invalid js syntax');
                    }
                    break;
            }

        }

        return $tree;
    }

    private static function variableBrackets(string $expression): ?string
    {
        $expression = \trim($expression);

        if (!\preg_match('/^(\w+)\((.*)\)$/', $expression, $matches)) {
            return null;
        }

        $fn = $matches[1];

        if (empty($matches[2])) {
            return $fn . '()';
        }

        $args = [];
        $temp = '';
        $inStr = false;

        $chars = \preg_split('//u', $matches[2]);
        foreach ($chars as $char) {
            switch ($char) {
                case '\'':
                    if ($inStr) {
                        if ($temp[-1] === '\\') {
                            $temp .= $char;
                        } else {
                            $args[] = $temp;
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
}