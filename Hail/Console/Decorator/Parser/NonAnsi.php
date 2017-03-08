<?php

namespace Hail\Console\Decorator\Parser;

class NonAnsi extends AbstractParser
{
	/**
	 * Strip the string of any tags
	 *
	 * @param  string $str
	 *
	 * @return string
	 */

	public function apply($str)
	{
		return preg_replace($this->tags->regex(), '', $str);
	}
}
