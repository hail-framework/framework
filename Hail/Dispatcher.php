<?php

namespace Hail;

use Hail\Exception\BadRequest;

/**
 * Class Dispatcher
 *
 * @package App\Panel
 */
class Dispatcher
{
	use DITrait;

	protected $application;
	protected $namespace;
	protected $forward = [];
	protected $current = [];
	protected $controller = [];

	public function __construct($app)
	{
		$this->application = $app;
		$this->namespace = 'App\\Controller\\' . $app;
	}

	public function run($controller, $action, $params)
	{
		$controller = $controller ?: 'Index';
		$action = $action ?: 'index';
		$params = $params ?: [];

		$this->current = [
			'controller' => ucfirst($controller),
			'action' => lcfirst($action),
			'params' => $params
		];

		list($class, $method) = $this->convert($controller, $action);

		$object = $this->controller($class);
		if (!method_exists($object, $method)) {
			throw new BadRequest('Action Not Defined', 404);
		} else if (method_exists($object, 'authorize') && !$object->authorize()) {
			throw new BadRequest('Unauthorized', 401);
		}

		$return = $object->$method();

		$outputType = $return['_type_'] ?? $this->config->get("app.output.{$this->application}");
		$this->output($outputType, $return);
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
		if ($return === false || $return === null) {
			return;
		}

		if ($this->request->getHeader('Origin') &&
			($domain = $this->config->get('app.cross_origin'))
		) {
			$this->response->addHeader('Access-Control-Allow-Origin', $domain);
			$this->response->addHeader('Access-Control-Allow-Credentials', 'true');
		}

		if ($return === true) {
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

				$this->output->json->send($return);
			break;

			case 'text':
				$this->output->text->send($return);
			break;

			case 'template':
				$name = $return['_template_'] ??
					$this->application . '/' . $this->current['controller'] . '/' . $this->current['action'];
				$this->output->template->send($name, $return);
			break;
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
		return strpos($class, $this->namespace) === 0 ? $class : $this->namespace . '\\' . ucfirst($class);
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

		return [$controllerClass, lcfirst($action) . 'Action'];
	}

	public function forward($to)
	{
		$app = $to['app'] ?? $this->application;

		if ($app !== $this->application) {
			$dispatcher = $this->app->getDispatcher($app);
			return $dispatcher->forward($to);
		} else {
			$controller = $to['controller'] ?? 'Index';
			$action = $to['action'] ?? 'Index';
			$params = $to['params'] ?? [];

			$this->forward[] = $this->current;
			$this->run($controller, $action, $params);
			return false;
		}
	}

	public function error($no, $msg = null)
	{
		$this->forward([
			'controller' => 'Error',
			'params' => [
				'error' => $no,
				'message' => $msg
			]
		]);

		return false;
	}

	public function current($type = null)
	{
		if ($type === null) {
			return $this->current;
		}

		return $this->current[$type] ?? null;
	}
}