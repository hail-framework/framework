<?php

namespace Hail\Template\Wrapper;

/**
 * Class StringObject
 *
 * @see     https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/String
 *
 * @package Hail\Template\Javascript
 */
class StringWrapper implements \ArrayAccess
{
    /**
     * @var string
     */
    private $value;

    /**
     * @var int
     */
    public $length;

    public function __construct(string $string)
    {
        $this->value = $string;
        $this->length = \mb_strlen($string);
    }

    public function __toString()
    {
        return $this->value;
    }

    public function withString($string, $length = null)
    {
        $new = clone $this;
        $new->value = $string;
        $new->length = $length ?? \mb_strlen($string);

        return $new;
    }

    /** offsetExists ( mixed $index )
     *
     * Similar to array_key_exists
     */
    public function offsetExists($index)
    {
        return $index >= 0 && $index < $this->length;
    }

    /* offsetGet ( mixed $index )
     *
     * Retrieves an array value
     */
    public function offsetGet($index)
    {
        return $this->withString(
            \mb_substr($this->value, $index, 1), 1
        );
    }

    /* offsetSet ( mixed $index, mixed $val )
     *
     * Sets an array value
     */
    public function offsetSet($index, $val)
    {
        $val = (string) $val;
        $this->value = \mb_substr($this->value, 0, $index) . $val . \mb_substr($this->value, $index);
        $this->length += \mb_strlen($val);
    }

    /* offsetUnset ( mixed $index )
     *
     * Removes an array value
     */
    public function offsetUnset($index)
    {
        $this->value = \mb_substr($this->value, 0, $index) . \mb_substr($this->value, $index + 1);
        --$this->length;
    }

    public function substr($start, $length)
    {
        return $this->withString(
            \mb_substr($this->value, $start, $length)
        );
    }

    public function substring($start, $end)
    {
        $index = \max(0, \min($start, $end));
        $end = \max(0, \max($start, $end));
        $length = $end - $index;

        return $this->substr($index, $length);
    }

    public function charAt($point)
    {
        return $this->offsetGet($point);
    }


    public function indexOf($needle, $offset = 0)
    {
        $pos = \mb_strpos($this->value, $needle, $offset);

        return $pos === false ? -1 : $pos;
    }

    public function lastIndexOf($needle, $offset = 0)
    {
        $pos = \mb_strrpos($this->value, $needle, $offset);

        return $pos === false ? -1 : $pos;
    }

    public function match($regex)
    {
        \preg_match_all($regex, $this->value, $matches, PREG_PATTERN_ORDER);

        return new ArrayWrapper($matches[0]);
    }

    public function replace($search, $replace, $regex = false)
    {
        if ($search === '') {
            return clone $this;
        }

        return $this->withString(
            $regex ? \preg_replace($search, $replace, $this->value) :
                \str_replace($search, $replace, $this->value)
        );
    }

    public function search($search, $regex = false)
    {
        if ($regex) {
            $first = \preg_split($search, $this->value)[0];

            return $first === $this->value ? -1 : \mb_strlen($first) - 1;
        }

        return $this->indexOf($search);
    }

    public function slice($start, $end = null)
    {
        if ($start >= 0) {
            if ($end >= 0) {
                return $this->substring($start, $end);
            }

            return $this->substr($start, $end);
        }

        if ($end < 0 && $end > $start) {
            return $this->substr($start, $end);
        }

        return $this->withString('', 0);
    }

    public function toLowerCase()
    {
        return $this->withString(
            \mb_strtolower($this->value),
            $this->length
        );
    }

    public function toUpperCase()
    {
        return $this->withString(
            \mb_strtoupper($this->value),
            $this->length
        );
    }

    public function split($at = '')
    {
        $at = (string) $at;

        if ($at === '') {
            return new ArrayWrapper(\str_split($this->value));
        }

        return new ArrayWrapper(\explode($at, $this->value));
    }

    public function trim($charlist = null)
    {
        return $this->withString(
            \trim($this->value, $charlist)
        );
    }

    public function ltrim($charlist = null)
    {
        return $this->withString(
            \ltrim($this->value, $charlist)
        );
    }

    public function rtrim($charlist = null)
    {
        return $this->withString(
            \rtrim($this->value, $charlist)
        );
    }

    public function concat(...$args)
    {
        $str = $this->value;
        foreach ($args as $v) {
            $str .= (string) $v;
        }

        return $this->withString($str);
    }

    public function startWith($search, int $offset = 0)
    {
        return \mb_strpos($this->value, (string) $search) === $offset;
    }

    public function endWith($search, int $length = null)
    {
        if ($length === null) {
            $value = $this->value;
        } else {
            $value = \mb_substr($this->value, 0, $length);
        }

        return \mb_strpos($value, $search, \mb_strlen($search)) !== false;
    }

    public function includes($search, int $offset = 0)
    {
        return \mb_strpos($this->value, $search, $offset) !== false;
    }

    public function repeat($count)
    {
        $count = (int) $count;
        return $this->withString(
            \str_repeat($this->value, $count)
        );
    }
}