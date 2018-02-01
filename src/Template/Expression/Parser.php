<?php

namespace Hail\Template\Expression;


class Parser
{
    private const SUPPORT_TOKEN = [
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_DEC,
        T_INC,
        T_IS_EQUAL,
        T_IS_GREATER_OR_EQUAL,
        T_IS_IDENTICAL,
        T_IS_NOT_EQUAL,
        T_IS_NOT_IDENTICAL,
        T_IS_SMALLER_OR_EQUAL,
        T_LNUMBER,
        T_DNUMBER,
        T_STRING,
        T_CONSTANT_ENCAPSED_STRING,
    ];

    /**
     * @param string $expression
     *
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function parse(string $expression)
    {
        if (\strpos($expression, '<?') === 0) {
            throw new \InvalidArgumentException('Expression syntax error');
        }

        $expression = '<?php ' . $expression;

        $tokens = \token_get_all($expression);

        if (!isset($tokens[0][0]) || $tokens[0][0] !== T_OPEN_TAG) {
            throw new \InvalidArgumentException('Expression syntax error');
        }

        $temp = [];
        $level = 0;

        $n = \count($tokens);
        for ($i = 1; $i < $n; ++$i) {
            $token = $tokens[$i];

            $type = null;
            if (\is_array($token)) {
                $type = $token[0];

                if ($type === T_WHITESPACE) {
                    continue;
                }

                if (!\in_array($type, self::SUPPORT_TOKEN, true)) {
                    throw new \InvalidArgumentException('Expression contains not support token "' . \token_name($type) . '""');
                }
            }

            switch ($token) {
                case '?':
                    $part = $temp[$level];

                    if ($level === 0) {
                        $temp[$level++] = [$part, new Token($token)];
                    } else {
                        $parentLevel = $level - 1;
                        $temp[$parentLevel][] = $part;
                        $temp[$parentLevel][] = new Token($token);
                    }

                    $temp[$level] = [];
                    break;

                case ':':
                    if ($level === 0) {
                        throw new \InvalidArgumentException('Expression ternary syntax error');
                    }

                    $part = $temp[$level];

                    $parentLevel = $level - 1;
                    $temp[$parentLevel][] = $part;
                    $temp[$parentLevel][] = new Token($token);

                    $temp[$level] = [];
                    break;

                case ';':
                    if ($level > 0) {
                        $part = $temp[$level];
                        unset($temp[$level]);

                        $temp[--$level][] = $part;
                    }

                    break;
                case ')':
                case ']':
                    $temp[$level][] = new Token($token);
                    $part = $temp[$level];
                    unset($temp[$level]);

                    $temp[--$level][] = $part;
                    break;

                case '(':
                case '[':
                    $temp[++$level] = [new Token($token)];
                    break;

                default:
                    $temp[$level][] = new Token($token);
            }
        }

        if (\count($temp) > 1) {
            throw new \InvalidArgumentException('Expression syntax error');
        }

        $tree = $temp[0];
    }
}