<?php
/*
 * This file is part of the GetOptionKit package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console\Option;

use ArrayIterator;
use IteratorAggregate;
use Countable;
use LogicException;
use InvalidArgumentException;
use Hail\Console\Exception\OptionConflictException;

class OptionCollection implements IteratorAggregate, Countable
{
    public $data = [];

    /**
     * @var Option[]
     *
     * read-only property
     */
    public $longOptions = [];

    /**
     * @var Option[]
     *
     * read-only property
     */
    public $shortOptions = [];

    /**
     * @var Option[]
     *
     * read-only property
     */
    public $options = [];

    public function __construct()
    {
        $this->data = [];
    }

    public function __clone()
    {
        foreach ($this->data as $k => $v) {
            $this->data[$k] = clone $v;
        }
        foreach ($this->longOptions as $k => $v) {
            $this->longOptions[$k] = clone $v;
        }
        foreach ($this->shortOptions as $k => $v) {
            $this->shortOptions[$k] = clone $v;
        }
        foreach ($this->options as $k => $v) {
            $this->options[$k] = clone $v;
        }
    }

    public function merge(OptionCollection $c)
    {
        $this->data = array_merge($this->data, $c->data);
        $this->longOptions = array_merge($this->longOptions, $c->longOptions);
        $this->shortOptions = array_merge($this->shortOptions, $c->shortOptions);
        $this->options = array_merge($this->options, $c->options);
    }

    /**
     * add( [spec string], [desc string], [key string] ).
     *
     * add( [option object] )
     *
     * @param string|Option $spec
     * @param string|null   $desc
     * @param string|null   $key
     *
     * @return Option
     */
    public function add($spec, string $desc = null, string $key = null): Option
    {
        if ($spec instanceof Option) {
            $this->addOption($spec);

            return $spec;
        }

        if (is_string($spec)) {
            // parse spec string
            $spec = new Option($spec, $desc);

            if ($key) {
                $spec->key = $key;
            }

            $this->addOption($spec);

            return $spec;
        }

        throw new LogicException('Unknown Spec Type');
    }

    /**
     * Add option object.
     *
     * @param Option $spec the option object.
     *
     * @throws InvalidArgumentException
     * @throws OptionConflictException
     */
    public function addOption(Option $spec)
    {
        $this->data[$spec->getId()] = $spec;
        if ($spec->long) {
            if (isset($this->longOptions[$spec->long])) {
                throw new OptionConflictException('Option conflict: --' . $spec->long . ' is already defined.');
            }
            $this->longOptions[$spec->long] = $spec;
        }
        if ($spec->short) {
            if (isset($this->shortOptions[$spec->short])) {
                throw new OptionConflictException('Option conflict: -' . $spec->short . ' is already defined.');
            }
            $this->shortOptions[$spec->short] = $spec;
        }
        $this->options[] = $spec;
        if (!$spec->long && !$spec->short) {
            throw new InvalidArgumentException('Neither long option name nor short name is not given.');
        }
    }

    public function getLongOption($name)
    {
        return $this->longOptions[$name] ?? null;
    }

    public function getShortOption($name)
    {
        return $this->shortOptions[$name] ?? null;
    }

    /* Get spec by spec id */
    public function get($id)
    {
        if (isset($this->data[$id])) {
            return $this->data[$id];
        }

        if (isset($this->longOptions[$id])) {
            return $this->longOptions[$id];
        }

        if (isset($this->shortOptions[$id])) {
            return $this->shortOptions[$id];
        }

        return null;
    }

    public function find($name)
    {
        foreach ($this->options as $option) {
            if ($option->short === $name || $option->long === $name) {
                return $option;
            }
        }

        return null;
    }

    public function size()
    {
        return count($this->data);
    }

    public function all()
    {
        return $this->data;
    }

    public function toArray()
    {
        $array = [];
        foreach ($this->data as $k => $spec) {
            $item = [];
            if ($spec->long) {
                $item['long'] = $spec->long;
            }
            if ($spec->short) {
                $item['short'] = $spec->short;
            }
            $item['desc'] = $spec->desc;
            $array[] = $item;
        }

        return $array;
    }

    public function keys()
    {
        return array_merge(array_keys($this->longOptions), array_keys($this->shortOptions));
    }

    public function count()
    {
        return count($this->data);
    }

    public function getIterator()
    {
        return new ArrayIterator($this->data);
    }
}
