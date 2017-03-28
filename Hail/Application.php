<?php
namespace Hail;

use Hail\Exception\{
	ApplicationException,
	BadRequestException
};
use Hail\Tracy\Debugger;
use Hail\Facades\{
	Event,
	Router,
	Request,
	Response,
	Output
};

/**
 * Front Controller.
 *
 */
class Application
{
	private $dispatcher = [];

	public function run()
	{
		try {
			Event::trigger('app.start');
			$this->process();
			Event::trigger('app.end');
		} catch (\Exception $e) {
			Event::trigger('app.error');
			$this->processException($e);
		} finally {
			Event::trigger('app.shutdown');
		}
	}

	private function process()
	{
		$method = Request::getMethod();
		$result = Router::dispatch(
			$method,
			Request::getPathInfo()
		);

		if (isset($result['error'])) {
			throw new BadRequestException('Router Error', $result['error']);
		}

		$app = $result['handler']['app'] ?? '';
		$controller = $result['handler']['controller'] ?? '';
		$action = $result['handler']['action'] ?? '';

		$dispatcher = $this->getDispatcher($app);
		$dispatcher->run($method, $controller, $action, $result['params']);
	}

	/**
	 * @param string $app
	 *
	 * @return Dispatcher
	 * @throws BadRequestException
	 */
	public function getDispatcher($app)
	{
		if (!isset($this->dispatcher[$app])) {
			return $this->dispatcher[$app] = new Dispatcher($app);
		}

		return $this->dispatcher[$app];
	}

	protected function processException(\Exception $e)
	{
		if (!$e instanceof ApplicationException) {
			throw $e;
		}

		$code = 500;
		$isBadRequest = $e instanceof BadRequestException;
		if ($isBadRequest) {
			$code = $e->getCode() ?: 404;
		} else {
			Response::disableWarnOnBuffer();
		}

		if (!Response::isSent()) {
			Response::setCode($code);
		}

		$msg = [
			403 => 'Access Denied',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			410 => 'Gone',
			500 => 'Server Error',
		];

		$msg = $msg[$code] ?? $e->getMessage();

		Output::json()->send([
			'ret' => $code,
			'msg' => $msg,
		]);

		if (!$isBadRequest) {
			Debugger::log($e, Debugger::EXCEPTION);
		}
	}
}