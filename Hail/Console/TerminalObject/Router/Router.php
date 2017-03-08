<?php

namespace Hail\Console\TerminalObject\Router;

use Hail\Console\Util\{
	ParserImportTrait, OutputImportTrait, UtilImportTrait
};

class Router
{
	use ParserImportTrait, OutputImportTrait, UtilImportTrait;

	/**
	 * An instance of the Dynamic Router class
	 *
	 * @var DynamicRouter $dynamic ;
	 */
	protected $dynamic;

	/**
	 * An instance of the Basic Router class
	 *
	 * @var BasicRouter $basic ;
	 */
	protected $basic;

	public function __construct()
	{
		$this->dynamic = new DynamicRouter();
		$this->basic = new BasicRouter();
	}

	/**
	 * Check if the name matches an existing terminal object
	 *
	 * @param string $name
	 *
	 * @return boolean
	 */
	public function exists($name)
	{
		return ($this->basic->exists($name) || $this->dynamic->exists($name));
	}

	/**
	 * Execute a terminal object using given arguments
	 *
	 * @param string $name
	 * @param mixed  $arguments
	 *
	 * @return null|\Hail\Console\TerminalObject\Basic\AbstractBasic
	 */
	public function execute($name, $arguments)
	{
		$router = $this->getRouter($name);

		$router->output($this->output);

		$obj = $this->getObject($router, $name, $arguments);

		$obj->parser($this->parser);
		$obj->util($this->util);

		return $router->execute($obj);
	}

	/**
	 * Get the object whether it's a string or already instantiated
	 *
	 * @param AbstractRouter $router
	 * @param string         $name
	 * @param array          $arguments
	 *
	 * @return \Hail\Console\TerminalObject\Dynamic\AbstractDynamic|\Hail\Console\TerminalObject\Basic\AbstractBasic
	 */
	protected function getObject($router, $name, $arguments)
	{
		$obj = $router->path($name);

		if (is_string($obj)) {
			$obj = new $obj(...$arguments);
		}

		if (method_exists($obj, 'arguments')) {
			$obj->arguments(...$arguments);
		}

		return $obj;
	}

	/**
	 * Determine which type of router we are using and return it
	 *
	 * @param string $name
	 *
	 * @return AbstractRouter|null
	 */
	protected function getRouter($name)
	{
		if ($this->basic->exists($name)) {
			return $this->basic;
		}

		if ($this->dynamic->exists($name)) {
			return $this->dynamic;
		}

		return null;
	}
}
