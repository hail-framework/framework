<?php

/**
 * This file is part of the Hail\Latte (https://Hail\Latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Latte\Runtime;

use Hail\Latte\Object;


/**
 * HTML literal.
 */
class Html extends Object implements IHtmlString
{
	/** @var string */
	private $value;


	public function __construct($value)
	{
		$this->value = (string) $value;
	}


	/**
	 * @return string
	 */
	public function __toString()
	{
		return $this->value;
	}

}
