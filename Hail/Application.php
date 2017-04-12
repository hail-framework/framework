<?php
namespace Hail;

use Hail\Facade\Config;
use Hail\Http\{
	Dispatcher, Emitter\Sapi, ServerRequest
};

class Application
{

	public function run()
	{
		$container = Bootstrap::init();

		$response = $container->get('dispatcher')->dispatch(ServerRequest::fromGlobals());

		(new Sapi())->emit($response);
	}

//	private function process()
//	{
//		$method = Request::getMethod();
//		$result = Router::dispatch(
//			$method,
//			Request::getPathInfo()
//		);
//
//		if (isset($result['error'])) {
//			throw new BadRequestException('Router Error', $result['error']);
//		}
//
//		$app = $result['handler']['app'] ?? '';
//		$controller = $result['handler']['controller'] ?? '';
//		$action = $result['handler']['action'] ?? '';
//
//		$dispatcher = $this->getDispatcher($app);
//		$dispatcher->run($method, $controller, $action, $result['params']);
//	}

//	/**
//	 * @param string $app
//	 *
//	 * @return Dispatcher
//	 * @throws BadRequestException
//	 */
//	public function getDispatcher($app)
//	{
//		if (!isset($this->dispatcher[$app])) {
//			return $this->dispatcher[$app] = new Dispatcher($app);
//		}
//
//		return $this->dispatcher[$app];
//	}
//
//	protected function processException(\Exception $e)
//	{
//		if (!$e instanceof ApplicationException) {
//			throw $e;
//		}
//
//		$code = 500;
//		$isBadRequest = $e instanceof BadRequestException;
//		if ($isBadRequest) {
//			$code = $e->getCode() ?: 404;
//		} else {
//			Response::disableWarnOnBuffer();
//		}
//
//		if (!Response::isSent()) {
//			Response::setCode($code);
//		}
//
//		$msg = [
//			403 => 'Access Denied',
//			404 => 'Not Found',
//			405 => 'Method Not Allowed',
//			410 => 'Gone',
//			500 => 'Server Error',
//		];
//
//		$msg = $msg[$code] ?? $e->getMessage();
//
//		Output::json()->send([
//			'ret' => $code,
//			'msg' => $msg,
//		]);
//
//		if (!$isBadRequest) {
//			Debugger::log($e, Debugger::EXCEPTION);
//		}
//	}
//
	public static function path(string $root, string $path = null)
	{
		if ($path === null || $path === '') {
			return $root;
		}

		if (strpos($path, '..') !== false) {
			throw new \InvalidArgumentException('Unable to get a directory higher than ROOT');
		}

		$path = str_replace('\\', '/', $path);
		if ($path[0] === '/') {
			$path = ltrim($path, '/');
		}

		return realpath($root . $path) ?: $root . $path;
	}
}