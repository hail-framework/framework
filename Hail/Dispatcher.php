<?php

namespace Hail;

use Hail\Exception\BadRequest;

/**
 * Class Dispatcher
 * @package App\Panel
 */
class Dispatcher
{
	use DITrait;

	protected $namespace;
	protected $current = [];
	protected $controller = [];
	protected $model = [];

	public function __construct($app)
	{
		$this->namespace = 'App\\' . $app;
	}

	public function run($controller, $action, $params)
	{
		$controller = $controller ?: 'Index';
		$action = $action ?: 'index';

		$this->current = [
			'controller' => $controller,
			'action' => $action,
		];

		list($class, $method) = $this->convert($controller, $action);

		$object = $this->controller($class);
		if (!method_exists($object, $method)) {
			throw new BadRequest('Action Not Defined', 404);
		}

		if (isset($params['param'])) {
			$object->$method($params['param']);
		} else {
			$object->$method();
		}
	}

	/**
	 * @param $class
	 * @return Controller
	 * @throws BadRequest
	 */
	protected function controller($class)
	{
		$class = $this->class($class);
		if (!isset($this->controller[$class])) {
			if (!is_subclass_of($class, __NAMESPACE__ . '\\Controller')) {
				throw new BadRequest('Controller Not Defined', 404);
			}
			return $this->controller[$class] = new $class($this);
		}

		return $this->controller[$class];
	}

	/**
	 * @param string $class
	 * @return string
	 */
	protected function class($class)
	{
		return strpos($class, $this->namespace) === 0 ? $class : $this->namespace . '\\' . $class;
	}

	/**
	 * @param string $controller
	 * @param string $action
	 * @return array
	 * @throws BadRequest
	 */
	protected function convert($controller, $action)
	{
		$controllerClass = $this->class($controller);
		$actionClass = $controllerClass . '\\' . ucfirst($action);

		if (class_exists($actionClass)) {
			return [$actionClass, 'indexAction'];
		}

		if (!class_exists($controllerClass)) {
			throw new BadRequest('Controller Not Defined', 404);
		}

		return [$controllerClass, $action . 'Action'];
	}

	/**
	 * __get
	 * @param string $name
	 * @return mixed
	 */
	protected function __getProperty($name)
	{
//		return $this->model($name);
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	protected function model($name)
	{
		if (!isset($this->model[$name])) {
			$class = $this->class('Model\\' . $name);
			if (!class_exists($class)) {
				throw new \RuntimeException("Model $name Not Defined");
			}
			return $this->model[$name] = new $class();
		}

		return $this->model[$name];
	}
}