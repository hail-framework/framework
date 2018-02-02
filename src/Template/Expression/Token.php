<?php

namespace Hail\Template\Expression;


class Token
{
    private const WHITESPACE = [
        '?', ':', '-', '*', '/', '<', '>',
        'T_BOOLEAN_AND',
        'T_BOOLEAN_OR',
        'T_IS_EQUAL',
        'T_IS_GREATER_OR_EQUAL',
        'T_IS_IDENTICAL',
        'T_IS_NOT_EQUAL',
        'T_IS_NOT_IDENTICAL',
        'T_IS_SMALLER_OR_EQUAL',
    ];

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $content;

    public function __construct($token)
    {
        if (\is_array($token)) {
            [$type, $this->content] = $token;
            $this->type = \token_name($type);
        } else {
            $this->type = $this->content = $token;
        }
    }

    public function is($name): bool
    {
        $name = (array) $name;

        foreach ($name as &$v) {
            if (\is_int($v)) {
                $v = \token_name($v);
            }
        }

        return \in_array($this->type, $name, true);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContent(): string
    {
        if (\in_array($this->type, self::WHITESPACE, true)) {
            return " {$this->content} ";
        }

        if ($this->type === ',') {
            return ', ';
        }

        return $this->content;
    }

    public function __toString()
    {
        return $this->getContent();
    }
}