<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;

use Hail\Utils\Arrays;
use Hail\Utils\ArrayTrait;

/**
 * HttpRequest provides access scheme for request sent via HTTP.
 *
 */
class Input implements \ArrayAccess
{
	use ArrayTrait;

	protected $request;

	/** @var array */
	protected $items = [];

	/** @var bool */
	protected $all = false;

	/** @var array */
	protected $del = [];


	public function __construct(Request $request)
	{
		$this->request = $request;
	}

	public function setAll(array $array)
	{
		$this->setMultiple($array);
		$this->all = true;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 */
	public function set($key, $value = null)
	{
		if (!$this->all) {
			Arrays::delete($this->del, $key);
		}
		Arrays::set($this->items, $key, $value);
	}

	public function delete($key)
	{
		if (!$this->all) {
			Arrays::set($this->del, $key, true);
		}
		Arrays::delete($this->items, $key);
	}

	public function get($key = null)
	{
		if ($key === null) {
			return $this->getAll();
		} elseif ($this->all) {
			return Arrays::get($this->items, $key);
		}

		if (Arrays::has($this->del, $key)) {
			return null;
		} elseif (($return = Arrays::get($this->items, $key)) !== null) {
			return $return;
		}

		if ($this->request->isJson()) {
			$return = $this->request->getJson($key);
		} elseif (!$this->request->isMethod('GET')) {
			if (
				strpos(
					$this->request->getHeader('CONTENT-TYPE'),
					'multipart/form-data'
				) === 0
			) {
				$return = $this->request->getFile($key);
			}

			$return = $return ?? $this->request->getPost($key);
		}

		$return = $return ?? $this->request->getQuery($key);

		if ($return !== null) {
			Arrays::set($this->items, $key, $return);
		}

		return $return;
	}

	public function getAll()
	{
		if ($this->all) {
			return $this->items;
		}

		$return = $this->items;
		if ($this->request->isJson()) {
			$return += $this->request->getJson() ?? [];
		} elseif (!$this->request->isMethod('GET')) {
			if (
				strpos(
					$this->request->getHeader('CONTENT-TYPE'),
					'multipart/form-data'
				) === 0
			) {
				$return += $this->request->getFile() ?? [];
			}

			$return += $this->request->getPost() ?? [];
		}

		$return += $this->request->getQuery();
		if ($this->del !== []) {
			$this->clear($return, $this->del);
		}

		$this->all = true;

		return $this->items = $return;
	}

	protected function clear(array &$array, array $del)
	{
		foreach ($del as $k => $v) {
			if (is_array($v) && isset($array[$k])) {
				$this->clear($array[$k], $v);
			} else {
				unset($array[$k]);
			}
		}
	}
}
