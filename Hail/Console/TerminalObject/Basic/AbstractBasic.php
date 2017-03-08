<?php

namespace Hail\Console\TerminalObject\Basic;

use Hail\Console\Util\{
	ParserImportTrait, UtilImportTrait
};

abstract class AbstractBasic
{
	use ParserImportTrait, UtilImportTrait;

	/**
	 * Set the property if there is a valid value
	 *
	 * @param string $key
	 * @param string $value
	 */
	protected function set(string $key, string $value)
	{
		if ($value !== '') {
			$this->$key = $value;
		}
	}

	/**
	 * Get the parser for the current object
	 *
	 * @return \Hail\Console\Decorator\Parser\AbstractParser
	 */
	public function getParser()
	{
		return $this->parser;
	}

	/**
	 * Check if this object requires a new line to be added after the output
	 *
	 * @return boolean
	 */
	public function sameLine()
	{
		return false;
	}

	abstract public function result();
}
