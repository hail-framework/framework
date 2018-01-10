<?php

namespace Hail\Template\Wrapper;

/**
 * Class ArrayObject
 *
 * @see     https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array
 *
 * @package Hail\Template\Javascript
 */
class ArrayWrapper implements \ArrayAccess, \IteratorAggregate
{
    /**
     * @var array
     */
    private $array;

    /**
     * @var int
     */
    public $length;

    public function __construct($array)
    {
        if ($array instanceof self) {
            $array = $array->array;
        } else {
            $array = (array) $array;
        }

        $this->array = $array;
        $this->length = \count($array);
    }

    public function withArray($array, $length = null)
    {
        if ($array instanceof self) {
            $array = $array->array;
        } else {
            $array = (array) $array;
        }

        $new = clone $this;
        $new->array = $array;
        $new->length = $length ?? \count($array);

        return $new;
    }

    public function offsetExists($offset)
    {
        return \array_key_exists($offset, $this->array);
    }

    public function offsetGet($offset)
    {
        $value = $this->array[$offset] ?? null;

        if ($value) {
            if (\is_array($value)) {
                return $this->withArray($value);
            }

            if (\is_string($value)) {
                return new StringWrapper($value);
            }
        }

        return $value;
    }


    public function offsetSet($offset, $value)
    {
        $this->array[$offset] = $value;
        if ($offset > $this->length) {
            for ($i = $this->length; $i < $offset; $i++) {
                $this->array[$i] = null;
            }

            $this->length = \count($this->array);
        }
    }


    public function offsetUnset($offset)
    {
        $this->array[$offset] = null;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->array);
    }

    public function concat(...$args)
    {
        return $this->withArray(
            \array_merge($this->array, ...$args)
        );
    }

    public function push(...$args)
    {
        foreach ($args as $v) {
            $this->array[] = $v;
            ++$this->length;
        }

        return $this;
    }

    public function pop()
    {
        $return = \array_pop($this->array);
        --$this->length;

        return $return;
    }

    public function shift()
    {
        $return = \array_shift($this->array);
        --$this->length;

        return $return;
    }

    public function join($paste = '')
    {
        return new StringWrapper(
            \implode($paste, $this->array)
        );
    }

    public function fill($value, $start = 0, int $end = null)
    {
        if ($end === null) {
            $end = $this->length;
        }

        return $this->withArray(
            \array_fill($start, $end - $start, $value)
        );
    }

    public function includes($search, $offset = 0)
    {
        return $this->indexOf($search, $offset) !== -1;
    }

    public function indexOf($search, $offset = 0)
    {
        $array = $offset === 0 ? $this->array : \array_slice($this->array, $offset);
        $key = \array_search($search, $array, true);
        if ($key === false) {
            return -1;
        }

        return $key + $offset;
    }

    public function lastIndexOf($search, $offset = null)
    {
        $array = $offset === null ? $this->array : \array_slice($this->array, 0, $offset + 1);
        $key = \array_search($search, $array, true);

        if ($key === false) {
            return -1;
        }

        return $key;
    }

    public function reverse()
    {
        return $this->withArray(
            \array_reverse($this->array),
            $this->length
        );
    }

    public function slice($start = 0, $end = null)
    {
        if ($end === null) {
            $end = $this->length;
        }

        return $this->withArray(
            \array_slice($this->array, $start, $end - $start)
        );
    }

    public function sort()
    {
        \sort($this->array);

        return $this;
    }

    public function toString()
    {
        return new StringWrapper(\implode(',', $this->array));
    }

    public function unshift(...$args)
    {
       \array_unshift($this->array, ...$args);
        $this->length += \count($args);

        return $this;
    }

    public function splice($start, $delete = 0, ...$items)
    {
        $remove = \array_splice($this->array, $start, $delete, ...$items);
        $this->length = \count($this->array);

        return $this->withArray($remove);
    }
}