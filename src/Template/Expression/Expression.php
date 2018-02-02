<?php

namespace Hail\Template\Expression;

class Expression
{
    private const TAG_QUOTE = '#@!QUOTE!@#';

    /**
     * @var string[] expression cache
     */
    private static $cache;

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
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function parse(string $expression): string
    {
        $expression = \trim($expression);

        if ($expression === '' || \is_numeric($expression)) {
            return $expression;
        }

        if (isset(self::$cache[$expression])) {
            return self::$cache[$expression];
        }

        $tree = self::treeStruct($expression);

        return self::$cache[$expression] = self::transform($tree);
    }

    /**
     * @param string $expression
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    private static function treeStruct(string $expression): array
    {
        if (\strpos($expression, '<?') === 0 || $expression[-1] === ';') {
            throw new \InvalidArgumentException('Expression syntax error');
        }

        $expression = '<?php ' . $expression . ';';

        $tokens = \token_get_all($expression);

        $temp = [];
        $level = 0;

        $n = \count($tokens);
        $i = 1; // skip T_OPEN_TAG
        for (; $i < $n; ++$i) {
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

                if ($type === T_INC && ($tokens[$i - 1] === '+' || $tokens[$i + 1] === '+')) {
                    throw new \InvalidArgumentException('Expression contains error code: "+++');
                }

                if ($type === T_DEC && ($tokens[$i - 1] === '-' || $tokens[$i + 1] === '-')) {
                    throw new \InvalidArgumentException('Expression contains error code: "---');
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
                    ++$i;
                    break 2;
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

        if ($i !== $n) {
            throw new \InvalidArgumentException('The code has multi-line expression');
        }

        if (\count($temp) > 1) {
            throw new \InvalidArgumentException('Expression syntax error');
        }

        return $temp[0];
    }

    /**
     * @param array $tree
     *
     * @return string
     * @throws \InvalidArgumentException
     */
    private static function transform(array $tree): string
    {
        $n = \count($tree);

        $inObject = $usePlusFun = false;
        $expType = null;

        $return = [];
        for ($i = 0; $i < $n; ++$i) {
            $token = $tree[$i];

            if (\is_array($token)) {
                $return[] = self::transform($token);
                continue;
            }

            /* @var Token $token */
            switch ($token->getType()) {
                case 'T_STRING':
                    if ($inObject === false) {
                        $code = '$' . $token->getContent();

                        $next = $tree[$i + 1] ?? null;
                        if ($next instanceof Token) {
                            if ($next->is('.')) {
                                $code = '$this->wrap(' . $code . ')';
                                $inObject = true;
                            }

                            if ($next->is([T_INC, T_DEC])) {
                                $code .= $next->getContent();
                                ++$i;
                            }
                        }

                        $return[] = $code;
                    } else {
                        $return[] = $token->getContent();
                    }
                    continue 2;

                case '.':
                    if ($inObject === false) {
                        throw new \InvalidArgumentException('Expression syntax error');
                    }

                    $return[] = '->';
                    continue 2;

                case 'T_LNUMBER':
                case 'T_DNUMBER':
                case 'T_INC':
                case 'T_DEC':
                    $return[] = $token->getContent();

                    if (!$usePlusFun && $expType === null) {
                        $expType = 'int';
                    }
                    break;

                case 'T_CONSTANT_ENCAPSED_STRING':
                    $return[] = $token->getContent();

                    if (!$usePlusFun && $expType === null) {
                        $expType = 'string';
                    }
                    break;

                case '+':
                    switch ($expType) {
                        case 'int':
                            $return[] = ' + ';
                            break;

                        case 'string':
                            $return[] = ' . ';
                            break;

                        default:
                            if (!$usePlusFun) {
                                if ($return[0] === '(' || $return[0] === '[') {
                                    \array_splice($return, 1, 0, static::class . '::plus(');
                                } else {
                                    \array_unshift($return, static::class . '::plus(');
                                }

                                $usePlusFun = true;
                            }
                            $return[] = ', ';
                            break;
                    }
                    break;


                case '*':
                case '/':
                    if (!$usePlusFun && $expType === null) {
                        $expType = 'int';
                    }

                    $return[] = $token->getContent();
                    break;

                case '-':
                    if ($usePlusFun) {
                        $return[] = ')';
                        $return[] = $token->getContent();
                        $usePlusFun = false;
                    } else {
                        $expType = 'int';
                        $return[] = $token->getContent();
                    }
                    break;

                case ')':
                case ']':
                    if ($usePlusFun) {
                        $return[] = ')';
                        $return[] = $token->getContent();
                        $usePlusFun = false;
                    } else {
                        $return[] = $token->getContent();
                    }
                    break;

                default:
                    $return[] = $token->getContent();
            }

            if ($inObject) {
                $inObject = false;
            }
        }

        if ($usePlusFun) {
            $return[] = ')';
        }

        return \implode('', $return);
    }

    /**
     * @param string|int $first
     * @param array      ...$args
     *
     * @return mixed
     */
    public static function plus($first, ...$args)
    {
        $return = $first;

        if (\is_string($first) || \method_exists($first, '__toString')) {
            foreach ($args as $arg) {
                $return .= $arg;
            }
        } else {
            foreach ($args as $arg) {
                $return += $arg;
            }
        }

        return $return;
    }

    public static function explode(string $delimiter, string $string, callable $callable = null): array
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