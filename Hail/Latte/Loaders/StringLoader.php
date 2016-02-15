<?php

/**
 * This file is part of the Hail\Latte (https://Hail\Latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Latte\Loaders;

use Hail\Latte\Object;
use Hail\Latte\ILoader;


/**
 * Template loader.
 */
class StringLoader extends Object implements ILoader
{

	/**
	 * Returns template source code.
	 * @return string
	 */
	public function getContent($content)
	{
		return $content;
	}


	/**
	 * @return bool
	 */
	public function isExpired($content, $time)
	{
		return FALSE;
	}


	/**
	 * Returns fully qualified template name.
	 * @return string
	 */
	public function getChildName($content, $parent = NULL)
	{
		return $content;
	}

}
