<?php

namespace Hail\Console\Decorator\Component;

class Color extends AbstractDecorator
{
	/**
	 * The available colors
	 *
	 * @var array
	 */
	protected $colors = [];

	public function __construct()
	{
		$this->defaults = [
			'default' => 39,
			'black' => 30,
			'red' => 31,
			'green' => 32,
			'yellow' => 33,
			'blue' => 34,
			'magenta' => 35,
			'cyan' => 36,
			'light_gray' => 37,
			'dark_gray' => 90,
			'light_red' => 91,
			'light_green' => 92,
			'light_yellow' => 93,
			'light_blue' => 94,
			'light_magenta' => 95,
			'light_cyan' => 96,
			'white' => 97,
		];

		$this->defaults();
	}

	/**
	 * Add a color into the mix
	 *
	 * @param string  $key
	 * @param integer $value
	 */
	public function add($key, $value)
	{
		$this->colors[$key] = (int) $value;
	}

	/**
	 * Retrieve all of available colors
	 *
	 * @return array
	 */
	public function all()
	{
		return $this->colors;
	}

	/**
	 * Get the code for the color
	 *
	 * @param  string $val
	 *
	 * @return string
	 */
	public function get($val)
	{
		// If we already have the code, just return that
		if (is_numeric($val)) {
			return $val;
		}

		return $this->colors[$val] ?? null;
	}

	/**
	 * Set the current color
	 *
	 * @param  string $val
	 *
	 * @return boolean
	 */
	public function set($val)
	{
		$code = $this->get($val);

		if ($code) {
			$this->current = [$code];

			return true;
		}

		return false;
	}
}
