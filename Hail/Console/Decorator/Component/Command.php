<?php

namespace Hail\Console\Decorator\Component;

class Command extends AbstractDecorator
{
	/**
	 * Commands that correspond to a color in the $colors property
	 *
	 * @var array
	 */
	public $commands = [];

	public function __construct()
	{
		$this->defaults = [
			'info' => 'green',
			'comment' => 'yellow',
			'whisper' => 'light_gray',
			'shout' => 'red',
			'error' => 'light_red',
		];

		$this->defaults();
	}

	/**
	 * Add a command into the mix
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function add($key, $value)
	{
		$this->commands[$key] = $value;
	}

	/**
	 * Retrieve all of the available commands
	 *
	 * @return array
	 */
	public function all()
	{
		return $this->commands;
	}

	/**
	 * Get the style that corresponds to the command
	 *
	 * @param  string $val
	 *
	 * @return string
	 */
	public function get($val)
	{
		return $this->commands[$val] ?? null;
	}

	/**
	 * Set the currently used command
	 *
	 * @param  string $val
	 *
	 * @return string|false
	 */
	public function set($val)
	{
		// Return the code because it is a string corresponding
		// to a property in another class
		return $this->get($val) ?: false;
	}
}
