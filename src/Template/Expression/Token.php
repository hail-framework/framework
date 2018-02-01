<?php

namespace Hail\Template\Expression;


class Token
{
    private const WHITESPACE = [
        '?', ':', '-', '*', '/', '<', '>',
        T_BOOLEAN_AND,
        T_BOOLEAN_OR,
        T_IS_EQUAL,
        T_IS_GREATER_OR_EQUAL,
        T_IS_IDENTICAL,
        T_IS_NOT_EQUAL,
        T_IS_NOT_IDENTICAL,
        T_IS_SMALLER_OR_EQUAL,
    ];

    /**
     * @var int|null
     */
    private $type;

    /**
     * @var string
     */
    private $content;

    public function __construct($token)
    {
        if (\is_array($token)) {
            [$this->type, $this->content] = $token;
        } else {
            $this->type = $this->content = $token;
        }
    }

    public function is($type): bool
    {
        return $this->type === $type;
    }

    public function __toString()
    {
        if (\in_array($this->type, self::WHITESPACE, true)) {
            return " {$this->content} ";
        }

        if ($this->type === ',') {
            return ', ';
        }

        return $this->content;
    }
}