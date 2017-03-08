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

namespace Hail\Console\Input;


use ArrayIterator;
use IteratorAggregate;
use Countable;
use LogicException;

/**
 * Class Collection
 *
 * @package Hail\Console
 */
class Collection implements IteratorAggregate, Countable
{
	protected $data = [];

	/**
	 * @var [string => Option]
	 *
	 * read-only property
	 */
	protected $longOptions = [];

	/**
	 * @var [string => Option]
	 *
	 * read-only property
	 */
	protected $shortOptions = [];

	/**
	 * @var Option[]
	 *
	 * read-only property
	 */
	protected $options = [];

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

	/**
	 * add( [spec string], [desc string] ).
	 *
	 * add( [option object] )
	 *
	 * @param array ...$args
	 *
	 * @return Option|mixed
	 * @throws LogicException
	 */
	public function add(...$args)
	{
		$first = $args[0];

		if ($first instanceof Option) {
			$this->addOption($first);

			return $first;
		} else if (is_string($first)) {

			$specString = $args[0];
			$desc = $args[1] ?? null;
			$key = $args[2] ?? null;

			// parse spec string
			$spec = new Option($specString);
			if ($desc) {
				$spec->desc($desc);
			}
			if ($key) {
				$spec->key = $key;
			}
			$this->addOption($spec);

			return $spec;
		} else {
			throw new LogicException('Unknown Spec Type');
		}
	}

	/**
	 * Add option object.
	 *
	 * @param Option $spec the option object.
	 *
	 * @throws LogicException
	 */
	public function addOption(Option $spec)
	{
		$this->data[$spec->getId()] = $spec;
		if ($spec->long) {
			$this->longOptions[$spec->long] = $spec;
		}
		if ($spec->short) {
			$this->shortOptions[$spec->short] = $spec;
		}
		$this->options[] = $spec;
		if (!$spec->long && !$spec->short) {
			throw new LogicException('Neither long option name nor short name is not given.');
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
		return $this->data[$id] ??
			($this->longOptions[$id] ??
				($this->shortOptions[$id] ?? null)
			);
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
		return array_merge(
			array_keys($this->longOptions),
			array_keys($this->shortOptions)
		);
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
