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
	protected $model = [];

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
			'controller' => $controller,
			'action' => $action,
			'params' => $params
		];

		list($class, $method) = $this->convert($controller, $action);

		$object = $this->controller($class);
		if (!method_exists($object, $method)) {
			throw new BadRequest('Action Not Defined', 404);
		}

		$return = $object->$method();

		$outputType = $return['_type_'] ?? $this->config->get("env.output.{$this->application}");
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

	public function output($type, $return)
	{
		if ($return === false || $return === null) {
			return;
		}

		if (!empty($this->request->getHeader('Origin'))) {
			$domain = $this->config->get('app.cross_origin');
			if (!empty($domain)) {
				$this->response->addHeader('Access-Control-Allow-Origin', $domain);
				$this->response->addHeader('Access-Control-Allow-Credentials', 'true');
			}
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

	public function forward($to)
	{
		$controller = $to['controller'] ?? 'Index';
		$action = $to['action'] ?? 'Index';
		$params = $to['params'] ?? [];

		$this->forward[] = $this->current;

		$this->run($controller, $action, $params);
		return false;
	}

	public function error($no)
	{
		$this->forward([
			'controller' => 'Error',
			'params' => [
				'error' => $no
			]
		]);

		return false;
	}
}