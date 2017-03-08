<?php

namespace Hail\Console\Decorator;

use Hail\Console\Decorator\Parser\{
	Ansi, NonAnsi
};
use Hail\Console\Util\Helper;
use Hail\Console\Util\System\AbstractSystem;

/**
 * @method void addColor(string $color, integer $code)
 * @method void addFormat(string $format, integer $code)
 * @method void addCommand(string $command, mixed $style)
 */
class Style
{
	/**
	 * An array of Decorator objects
	 *
	 * @var Component\AbstractDecorator[] $style
	 */
	protected $style = [];

	protected $parser;

	/**
	 * An array of the current styles applied
	 *
	 * @var array $current
	 */
	protected $current = [];

	public function __construct()
	{
		$this->style = [
			'format' => new Component\Format(),
			'color' => new Component\Color(),
			'background' => new Component\BackgroundColor(),
			'command' => new Component\Command(),
		];
	}

	/**
	 * Get all of the styles available
	 *
	 * @return array
	 */
	public function all()
	{
		$all = [];

		foreach ($this->style as $style) {
			$all[] = $this->convertToCodes($style->all());
		}

		return call_user_func_array('array_merge', $all);
	}

	/**
	 * Attempt to get the corresponding code for the style
	 *
	 * @param  mixed $key
	 *
	 * @return mixed
	 */
	public function get($key)
	{
		foreach ($this->style as $style) {
			if ($code = $style->get($key)) {
				return $code;
			}
		}

		return false;
	}

	/**
	 * Attempt to set some aspect of the styling,
	 * return true if attempt was successful
	 *
	 * @param  string $key
	 *
	 * @return boolean
	 */
	public function set($key)
	{
		foreach ($this->style as $style) {
			if ($code = $style->set($key)) {
				return $this->validateCode($code);
			}
		}

		return false;
	}

	/**
	 * Reset the current styles applied
	 *
	 */
	public function reset()
	{
		foreach ($this->style as $style) {
			$style->reset();
		}
	}

	/**
	 * Get a new instance of the Parser class based on the current settings
	 *
	 * @param AbstractSystem $system
	 *
	 * @return \Hail\Console\Decorator\Parser\AbstractParser
	 */
	public function parser(AbstractSystem $system)
	{
		$current = $this->current();
		$tags = new Tags($this->all());

		if ($system->hasAnsiSupport()) {
			return new Ansi($current, $tags);
		}

		return new NonAnsi($current, $tags);
	}

	/**
	 * Compile an array of the current codes
	 *
	 * @return array
	 */
	public function current()
	{
		$current = [];

		foreach ($this->style as $style) {
			$current[] = Helper::toArray($style->current());
		}

		$current = call_user_func_array('array_merge', $current);

		return array_values(
			array_filter($current)
		);
	}

	/**
	 * Make sure that the code is an integer, if not let's try and get it there
	 *
	 * @param mixed $code
	 *
	 * @return boolean
	 */
	protected function validateCode($code)
	{
		if (is_int($code)) {
			return true;
		}

		// Plug it back in and see what we get
		if (is_string($code)) {
			return $this->set($code);
		}

		if (is_array($code)) {
			return $this->validateCodeArray($code);
		}

		return false;
	}

	/**
	 * Validate an array of codes
	 *
	 * @param array $codes
	 *
	 * @return boolean
	 */
	protected function validateCodeArray(array $codes)
	{
		// Loop through it and add each of the properties
		$adds = [];

		foreach ($codes as $code) {
			$adds[] = $this->set($code);
		}

		// If any of them came back true, we're good to go
		return in_array(true, $adds, true);
	}

	/**
	 * Convert the array of codes to integers
	 *
	 * @param array $codes
	 *
	 * @return array
	 */
	protected function convertToCodes(array $codes)
	{
		foreach ($codes as $key => $code) {
			if (is_int($code)) {
				continue;
			}

			$codes[$key] = $this->getCode($code);
		}

		return $codes;
	}

	/**
	 * Retrieve the integers from the mixed code input
	 *
	 * @param string|array $code
	 *
	 * @return integer|array
	 */
	protected function getCode($code)
	{
		if (is_array($code)) {
			return $this->getCodeArray($code);
		}

		return $this->get($code);
	}

	/**
	 * Retrieve an array of integers from the array of codes
	 *
	 * @param array $codes
	 *
	 * @return array
	 */
	protected function getCodeArray(array $codes)
	{
		foreach ($codes as $key => $code) {
			$codes[$key] = $this->get($code);
		}

		return $codes;
	}

	/**
	 * Parse the add method for the style they are trying to add
	 *
	 * @param string $method
	 *
	 * @return string
	 */
	protected function parseAddMethod($method)
	{
		return strtolower(substr($method, 3, strlen($method)));
	}

	/**
	 * Add a custom style
	 *
	 * @param string $style
	 * @param string $key
	 * @param string $value
	 */
	protected function add($style, $key, $value)
	{
		$this->style[$style]->add($key, $value);

		// If we are adding a color, make sure it gets added
		// as a background color too
		if ($style === 'color') {
			$this->style['background']->add($key, $value);
		}
	}

	/**
	 * Magic Methods
	 *
	 * List of possible magic methods are at the top of this class
	 *
	 * @param string $method
	 * @param array  $arguments
	 */
	public function __call($method, $arguments)
	{
		// The only methods we are concerned about are 'add' methods
		if (strpos($method, 'add') !== 0) {
			return;
		}

		$style = $this->parseAddMethod($method);

		if (isset($this->style[$style])) {
			[$key, $value] = $arguments;
			$this->add($style, $key, $value);
		}
	}
}
