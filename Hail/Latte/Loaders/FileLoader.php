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
class FileLoader extends Object implements ILoader
{
	private $directory;

	public function __construct($directory)
	{
		$this->directory = $directory;
	}

	/**
	 * Returns template source code.
	 * @return string
	 */
	public function getContent($file)
	{
		$file = $this->directory . $file;
		if (!is_file($file)) {
			throw new \RuntimeException("Missing template file '$file'.");

		} elseif ($this->isExpired($file, time())) {
			touch($file);
		}
		return file_get_contents($file);
	}


	/**
	 * @return bool
	 */
	public function isExpired($file, $time)
	{
		$file = $this->directory . $file;
		return @filemtime($file) > $time; // @ - stat may fail
	}


	/**
	 * Returns fully qualified template name.
	 * @return string
	 */
	public function getChildName($file, $parent = NULL)
	{
		$file = $this->directory . $file;
		if ($parent && !preg_match('#/|\\\\|[a-z][a-z0-9+.-]*:#iA', $file)) {
			$file = dirname($parent) . '/' . $file;
		}
		return $file;
	}

}
