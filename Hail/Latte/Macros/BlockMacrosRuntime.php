<?php

/**
 * This file is part of the Hail\Latte (https://Hail\Latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Latte\Macros;

use Hail\Latte\Object;
use Hail\Latte\Helpers;
use Hail\Latte\RuntimeException;


/**
 * Runtime helpers for block macros.
 */
class BlockMacrosRuntime extends Object
{

	/**
	 * Calls block.
	 * @return void
	 */
	public static function callBlock(\stdClass $context, $name, array $params)
	{
		if (empty($context->blocks[$name])) {
			$hint = isset($context->blocks) && ($t = Helpers::getSuggestion(array_keys($context->blocks), $name)) ? ", did you mean '$t'?" : '.';
			throw new RuntimeException("Cannot include undefined block '$name'$hint");
		}
		$block = reset($context->blocks[$name]);
		$block($context, $params);
	}


	/**
	 * Calls parent block.
	 * @return void
	 */
	public static function callBlockParent(\stdClass $context, $name, array $params)
	{
		if (empty($context->blocks[$name]) || ($block = next($context->blocks[$name])) === FALSE) {
			throw new RuntimeException("Cannot include undefined parent block '$name'.");
		}
		$block($context, $params);
		prev($context->blocks[$name]);
	}

}
