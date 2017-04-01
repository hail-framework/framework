<?php

namespace Hail;

use Hail\Exception\BadRequestException;
use Hail\Facade\{
	Config,
	Application,
	Request,
	Response,
	Output,
	Event
};

/**
 * Class Dispatcher
 *
 * @package App\Panel
 */
class Dispatcher
{
	protected $application;
	protected $namespace;
	protected $rest = '';
	protected $forward = [];
	protected $current = [];
	protected $controller = [];

	public function __construct($app)
	{
		$this->application = $app;
		$this->namespace = 'App\\Controller';

		if ($app) {
			$this->namespace .= '\\' . $app;
		}
	}

	public function run(string $rest, string $controller, string $action, array $params = null)
	{
		$this->rest = $rest;
		$controller = $controller ?: 'Index';
		$action = $action ?: 'index';
		$params = $params ?? [];

		$this->current = [
			'controller' => ucfirst($controller),
			'action' => lcfirst($action),
			'params' => $params,
		];

		list($class, $method) = $this->convert($controller, $action);

		$object = $this->controller($class);
		if (!method_exists($object, $method)) {
			throw new BadRequestException('Action Not Defined', 404);
		} else if (method_exists($object, 'authorize') && !$object->authorize()) {
			throw new BadRequestException('Unauthorized', 401);
		}

		switch ($rest) {
			case 'OPTIONS':
				$return = false;
				$outputType = 'blank';
				break;

			case 'GET':
			case 'POST':
			default:
				$return = $object->$method();
				$outputType = $return['_type_'] ?? Config::get("app.output.{$this->application}");
		}

		$this->output($outputType, $return);
	}

	public function getApplication()
	{
		return $this->application;
	}

	public function getController()
	{
		return $this->current['controller'] ?? null;
	}

	public function getAction()
	{
		return $this->current['action'] ?? null;
	}

	public function getParam($key = null)
	{
		$params = $this->current['params'] ?? [];
		if ($key === null) {
			return $params;
		} else {
			return $params[$key] ?? null;
		}
	}

	public function setParam($key, $value)
	{
		$this->current['params'][$key] = $value;
	}

	public function output($type, $return)
	{
		if ($return === null) {
			return;
		}

		if (($origin = Request::getHeader('Origin')) &&
			($allowOrigin = Config::get('app.allow_origin')) &&
			in_array($origin, (array) $allowOrigin, true)
		) {
			Response::setOrigin($origin);
		}

		if ($return === false) {
			return;
		} else if ($return === true) {
			$return = [];
		}

		switch ($type) {
			case 'json':
				if (!is_array($return)) {
					$return = ['ret' => 0, 'msg' => is_string($return) ? $return : 'OK'];
				} else if (!isset($return['ret'])) {
					$return['ret'] = 0;
					$return['msg'] = '';
				}

				Output::json()->send($return);
				break;

			case 'text':
				Output::text()->send($return);
				break;

			case 'template':
				$name = $return['_template_'] ??
					$this->application . '/' . $this->current['controller'] . '/' . $this->current['action'];
				Output::template()->send($name, $return);
				break;
		}
	}

	/**
	 * @param $class
	 *
	 * @return Controller
	 * @throws BadRequestException
	 */
	protected function controller($class)
	{
		$class = $this->class($class);
		if (!isset($this->controller[$class])) {
			if (!is_subclass_of($class, __NAMESPACE__ . '\\Controller')) {
				throw new BadRequestException('Controller Not Defined', 404);
			}

			return $this->controller[$class] = new $class($this);
		}

		return $this->controller[$class];
	}

	/**
	 * @param string $class
	 *
	 * @return string
	 */
	protected function class($class)
	{
		return strpos($class, $this->namespace) === 0 ? $class : $this->namespace . '\\' . ucfirst($class);
	}

	/**
	 * @param string $controller
	 * @param string $action
	 *
	 * @return array
	 * @throws BadRequestException
	 */
	protected function convert($controller, $action)
	{
		$controllerClass = $this->class($controller);
		$actionClass = $controllerClass . '\\' . ucfirst($action);

		if (class_exists($actionClass)) {
			return [$actionClass, 'indexAction'];
		}

		if (!class_exists($controllerClass)) {
			throw new BadRequestException('Controller Not Defined', 404);
		}

		return [$controllerClass, lcfirst($action) . 'Action'];
	}

	public function forward($to)
	{
		if ($this->application !== '') {
			$app = $to['app'] ?? $this->application;

			if ($app !== $this->application) {
				$dispatcher = Application::getDispatcher($app);

				return $dispatcher->forward($to);
			}
		}

		$controller = $to['controller'] ?? 'Index';
		$action = $to['action'] ?? 'Index';
		$params = $to['params'] ?? [];

		$this->forward[] = $this->current;
		$this->run($this->rest, $controller, $action, $params);

		return null;
	}

	public function error($code, $msg = null)
	{
		return $this->forward([
			'controller' => 'Error',
			'params' => [
				'error' => $code,
				'message' => $msg,
			],
		]);
	}
}