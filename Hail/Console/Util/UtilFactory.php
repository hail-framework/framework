<?php

namespace Hail\Console\Util;

use Hail\Console\Util\System\{
	AbstractSystem, Linux, Windows
};

class UtilFactory
{
	/**
	 * A instance of the appropriate System class
	 *
	 * @var AbstractSystem
	 */
	public $system;

	/**
	 * A instance of the Cursor class
	 *
	 * @var \Hail\Console\Util\Cursor
	 */
	public $cursor;

	public function __construct()
	{
		$this->getSystem();
		$this->cursor = new Cursor();
	}

	/**
	 * @return AbstractSystem
	 */
	public function getSystem()
	{
		return $this->system ?? (
			$this->system = stripos(PHP_OS, 'win') === 0 ?
				Windows::getInstance() :
				Linux::getInstance()
			);
	}

	/**
	 * Get the width of the terminal
	 *
	 * @return integer
	 */

	public function width()
	{
		return (int) $this->getDimension($this->system->width(), 80);
	}

	/**
	 * Get the height of the terminal
	 *
	 * @return integer
	 */

	public function height()
	{
		return (int) $this->getDimension($this->system->height(), 25);
	}

	/**
	 * Determine if the value is numeric, fallback to a default if not
	 *
	 * @param integer|null $dimension
	 * @param integer      $default
	 *
	 * @return integer
	 */

	protected function getDimension($dimension, $default)
	{
		return is_numeric($dimension) ? $dimension : $default;
	}
}
