<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/14 0019
 * Time: 15:30
 */

namespace Hail;

/**
 * Class DITrait
 *
 * @package Hail
 * @property-read DI $di
 * @property-read Cache\Embedded $embedded
 * @property-read Config $config
 * @property-read Loader\PSR4 $loader
 * @property-read Loader\Alias $alias
 * @property-read Router $router
 * @property-read I18N\Gettext $gettext
 * @property-read Http\Request $request
 * @property-read Http\Response $response
 * @property-read Event\Emitter $event
 * @property-read Application $app
 * @property-read Output $output
 */
Trait DITrait
{
	protected $di = null;

	public function __get($name)
	{
		if ($this->di === null) {
			$this->di = \DI::Instance();
		}

		if ($name === 'di') {
			return $this->di;
		} else if (isset($this->di[$name])) {
			return $this->di[$name];
		} else if (method_exists($this, '__getProperty')) {
			return $this->__getProperty($name);
		} else {
			throw new \RuntimeException("Property $name Not Defined");
		}
	}
}