<?php
namespace Hail\Console\TerminalObject\Router;

use Hail\Console\Util\OutputImportTrait;

abstract class AbstractRouter
{
	use OutputImportTrait;

	/**
	 * Get the full path for the class based on the key
	 *
	 * @param string $class
	 *
	 * @return string
	 */
	public function path($class)
	{
		return $this->getPath($this->shortName($class));
	}

	/**
	 * Determines if the requested class is a
	 * valid terminal object class
	 *
	 * @param  string $class
	 *
	 * @return boolean
	 */
	public function exists($class)
	{
		$class = $this->path($class);

		return (is_object($class) || class_exists($class));
	}

	/**
	 * Get the full path for the terminal object class
	 *
	 * @param  string $class
	 *
	 * @return string
	 */
	protected function getPath($class)
	{
		return 'Hail\\Console\\TerminalObject\\' . $this->pathPrefix() . '\\' . $class;
	}

	/**
	 * Get the class short name
	 *
	 * @param string $name
	 *
	 * @return string
	 */
	protected function shortName($name)
	{
		$name = str_replace('_', ' ', $name);
		$name = ucwords($name);

		return str_replace(' ', '', $name);
	}

	/**
	 * @param $obj
	 *
	 * @return null|\Hail\Console\TerminalObject\Basic\AbstractBasic|\Hail\Console\TerminalObject\Dynamic\AbstractDynamic
	 */
	abstract public function execute($obj);

	/**
	 * @return string
	 */
	abstract public function pathPrefix();
}
