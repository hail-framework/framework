<?php

/**
 * This file is part of the Hail\Latte (https://Hail\Latte.nette.org)
 * Copyright (c) 2008 David Grudl (https://davidgrudl.com)
 */

namespace Hail\Latte\Runtime;


interface IHtmlString
{

	/**
	 * @return string in HTML format
	 */
	function __toString();

}
