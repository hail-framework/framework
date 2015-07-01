<?php
/**
 * @from https://github.com/kevinlebrun/colors.php
 * Copyright (c) 2014 Kevin Le Brun <lebrun.k@gmail.com> Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Cli;

/**
 * Class Color
 * @package Hail\Cli
 */

class Color
{
	const FORMAT_PATTERN = '#<([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)>(.*?)</\\1?>#s';
	/** @link http://www.php.net/manual/en/functions.user-defined.php */
	const STYLE_NAME_PATTERN = '/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/';
	const ESC = "\033[";
	const ESC_SEQ_PATTERN = "\033[%sm";

	/**
	 * @var string
	 */
	protected $initial = '';

	/**
	 * @var string
	 */
	protected $wrapped = '';

	/**
	 * italic and blink may not work depending of your terminal
	 * @var array
	 */
	protected static $styles = array(
		'reset' => '0',
		'bold' => '1',
		'dark' => '2',
		'italic' => '3',
		'underline' => '4',
		'blink' => '5',
		'reverse' => '7',
		'concealed' => '8',
		'default' => '39',
		'black' => '30',
		'red' => '31',
		'green' => '32',
		'yellow' => '33',
		'blue' => '34',
		'magenta' => '35',
		'cyan' => '36',
		'light_gray' => '37',
		'dark_gray' => '90',
		'light_red' => '91',
		'light_green' => '92',
		'light_yellow' => '93',
		'light_blue' => '94',
		'light_magenta' => '95',
		'light_cyan' => '96',
		'white' => '97',
		'bg_default' => '49',
		'bg_black' => '40',
		'bg_red' => '41',
		'bg_green' => '42',
		'bg_yellow' => '43',
		'bg_blue' => '44',
		'bg_magenta' => '45',
		'bg_cyan' => '46',
		'bg_light_gray' => '47',
		'bg_dark_gray' => '100',
		'bg_light_red' => '101',
		'bg_light_green' => '102',
		'bg_light_yellow' => '103',
		'bg_light_blue' => '104',
		'bg_light_magenta' => '105',
		'bg_light_cyan' => '106',
		'bg_white' => '107',
	);

	public function __construct($string = '')
	{
		$this->setInternalState($string);
	}

	public function __invoke($string)
	{
		return $this->setInternalState($string);
	}

	public function __call($method, $args)
	{
		if (count($args) >= 1) {
			return $this->apply($method, $args[0]);
		}
		return $this->apply($method);
	}

	public function __get($name)
	{
		return $this->apply($name);
	}

	public function __toString()
	{
		return $this->wrapped;
	}

	/**
	 * Returns true if the stream supports colorization.
	 *
	 * Colorization is disabled if not supported by the stream:
	 *
	 *  -  Windows without Ansicon and ConEmu
	 *  -  non tty consoles
	 *
	 * @return bool true if the stream supports colorization, false otherwise
	 *
	 * @link https://github.com/symfony/Console/blob/master/Output/StreamOutput.php#L95-L102
	 * @codeCoverageIgnore
	 */
	public function isSupported()
	{
		if (DIRECTORY_SEPARATOR === '\\') {
			return false !== getenv('ANSICON') || 'ON' === getenv('ConEmuANSI');
		}
		return function_exists('posix_isatty') && @posix_isatty(STDOUT);
	}

	/**
	 * @codeCoverageIgnore
	 */
	public function are256ColorsSupported()
	{
		return DIRECTORY_SEPARATOR === '/' && false !== strpos(getenv('TERM'), '256color');
	}

	protected function setInternalState($string)
	{
		$this->initial = $this->wrapped = (string) $string;
		return $this;
	}

	protected function stylize($style, $text)
	{
		if (!$this->isSupported()) {
			return $text;
		}
		$style = strtolower($style);
		if (isset(static::$styles[$style])) {
			return $this->buildEscSeq(static::$styles[$style]) . $text . $this->buildEscSeq(static::$styles['reset']);
		}
		if (preg_match('/^((?:bg_)?)color\[(\d|[1-9]\d|1\d{2}|2[0-4]\d|25[0-5])\]$/', $style, $matches)) {
			$option = $matches[1] === 'bg_' ? 48 : 38;
			return $this->buildEscSeq("{$option};5;{$matches[2]}") . $text . $this->buildEscSeq(static::$styles['reset']);
		}
		throw new \InvalidArgumentException("Invalid style $style");
	}

	protected function buildEscSeq($style)
	{
		return sprintf(self::ESC_SEQ_PATTERN, $style);
	}

	public function apply($style, $text = null)
	{
		if ($text === null) {
			$this->wrapped = $this->stylize($style, $this->wrapped);
			return $this;
		}
		return $this->stylize($style, $text);
	}

	public function fg($color, $text = null)
	{
		return $this->apply($color, $text);
	}

	public function bg($color, $text = null)
	{
		return $this->apply('bg_' . $color, $text);
	}

	public function highlight($color, $text = null)
	{
		return $this->bg($color, $text);
	}

	public function reset()
	{
		$this->wrapped = $this->initial;
		return $this;
	}

	public function center($width = 80, $text = null)
	{
		if ($text === null) {
			$text = $this->wrapped;
		}
		$centered = '';
		foreach (explode(PHP_EOL, $text) as $line) {
			$line = trim($line);
			$lineWidth = strlen($line) - mb_strlen($line, 'UTF-8') + $width;
			$centered .= str_pad($line, $lineWidth, ' ', STR_PAD_BOTH) . PHP_EOL;
		}
		$this->setInternalState(trim($centered, PHP_EOL));
		return $this;
	}

	protected function stripColors($text)
	{
		return preg_replace('/' . preg_quote(self::ESC) . '\d+m/', '', $text);
	}

	public function clean($text = null)
	{
		if ($text === null) {
			$this->wrapped = $this->stripColors($this->wrapped);
			return $this;
		}
		return $this->stripColors($text);
	}

	public function strip($text = null)
	{
		return $this->clean($text);
	}

	public function isAValidStyleName($name)
	{
		return preg_match(self::STYLE_NAME_PATTERN, $name);
	}

	protected function colorizeText($text)
	{
		return preg_replace_callback(self::FORMAT_PATTERN, array($this, 'replaceStyle'), $text);
	}

	/**
	 * @link https://github.com/symfony/Console/blob/master/Formatter/OutputFormatter.php#L124-162
	 */
	public function colorize($text = null)
	{
		if ($text === null) {
			$this->wrapped = $this->colorizeText($this->wrapped);
			return $this;
		}
		return $this->colorizeText($text);
	}

	protected function replaceStyle($matches)
	{
		return $this->apply($matches[1], $this->colorize($matches[2]));
	}
}