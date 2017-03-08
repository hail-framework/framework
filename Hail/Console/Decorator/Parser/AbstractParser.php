<?php

namespace Hail\Console\Decorator\Parser;

use Hail\Console\Decorator\Tags;

abstract class AbstractParser
{
	/**
	 * An array of the currently applied codes
	 *
	 * @var array $current ;
	 */
	protected $current = [];

	/**
	 * An array of the tags that should be searched for
	 * and their corresponding replacements
	 *
	 * @var \Hail\Console\Decorator\Tags $tags
	 */
	public $tags;

	public function __construct(array $current, Tags $tags)
	{
		$this->current = $current;
		$this->tags = $tags;
	}

	/**
	 * Wrap the string in the current style
	 *
	 * @param  string $str
	 *
	 * @return string
	 */
	abstract public function apply($str);
}
