<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/7/19 0019
 * Time: 22:14
 */

namespace Hail;

/**
 * Front Controller.
 *
 * @property-read Http\Request $request
 * @property-read IPresenter $presenter
 * @property-read IRouter $router
 * @property-read IPresenterFactory $presenterFactory
 */
class Application
{
	/** @var int */
	public static $maxLoop = 20;

	/** @var bool enable fault barrier? */
	public $catchExceptions;

	/** @var string */
	public $errorPresenter;

	/** @var callable[]  function (Application $sender); Occurs before the application loads presenter */
	public $onStartup;

	/** @var callable[]  function (Application $sender, \Exception $e = NULL); Occurs before the application shuts down */
	public $onShutdown;

	/** @var callable[]  function (Application $sender, Request $request); Occurs when a new request is received */
	public $onRequest;

	/** @var callable[]  function (Application $sender, Presenter $presenter); Occurs when a presenter is created */
	public $onPresenter;

	/** @var callable[]  function (Application $sender, IResponse $response); Occurs when a new response is ready for dispatch */
	public $onResponse;

	/** @var callable[]  function (Application $sender, \Exception $e); Occurs when an unhandled exception occurs in the application */
	public $onError;

	/** @var Request[] */
	private $requests = array();

	/** @var IPresenter */
	private $presenter;

	/** @var Http\Request */
	private $httpRequest;

	/** @var Http\Response */
	private $httpResponse;

	/** @var IPresenterFactory */
	private $presenterFactory;

	/** @var IRouter */
	private $router;
	private $di;


	public function __construct($di)
	{
		$this->di = $di;
	}

	/**
	 * Dispatch a HTTP request to a front controller.
	 * @return void
	 */
	public function run()
	{
		try {
			$this->onStartup($this);
			$this->processRequest($this->createInitialRequest());
			$this->onShutdown($this);

		} catch (\Exception $e) {
			$this->onError($this, $e);
			if ($this->catchExceptions && $this->errorPresenter) {
				try {
					$this->processException($e);
					$this->onShutdown($this, $e);
					return;

				} catch (\Exception $e) {
					$this->onError($this, $e);
				}
			}
			$this->onShutdown($this, $e);
			throw $e;
		}
	}


	/**
	 * @return Request
	 */
	public function createInitialRequest()
	{
		$request = $this->router->match($this->httpRequest);

		if (!$request instanceof Request) {
			throw new BadRequestException('No route for HTTP request.');

		} elseif (strcasecmp($request->getPresenterName(), $this->errorPresenter) === 0) {
			throw new BadRequestException('Invalid request. Presenter is not achievable.');
		}

		try {
			$name = $request->getPresenterName();
			$this->presenterFactory->getPresenterClass($name);
		} catch (InvalidPresenterException $e) {
			throw new BadRequestException($e->getMessage(), 0, $e);
		}

		return $request;
	}


	/**
	 * @return void
	 */
	public function processRequest(Request $request)
	{
		if (count($this->requests) > self::$maxLoop) {
			throw new ApplicationException('Too many loops detected in application life cycle.');
		}

		$this->requests[] = $request;
		$this->onRequest($this, $request);

		$this->presenter = $this->presenterFactory->createPresenter($request->getPresenterName());
		$this->onPresenter($this, $this->presenter);
		$response = $this->presenter->run($request);

		if ($response instanceof Responses\ForwardResponse) {
			$this->processRequest($response->getRequest());

		} elseif ($response) {
			$this->onResponse($this, $response);
			$response->send($this->httpRequest, $this->httpResponse);
		}
	}


	/**
	 * @return void
	 */
	public function processException(\Exception $e)
	{
		if (!$e instanceof BadRequestException && $this->httpResponse instanceof Nette\Http\Response) {
			$this->httpResponse->warnOnBuffer = FALSE;
		}
		if (!$this->httpResponse->isSent()) {
			$this->httpResponse->setCode($e instanceof BadRequestException ? ($e->getCode() ?: 404) : 500);
		}

		$args = array('exception' => $e, 'request' => end($this->requests) ?: NULL);
		if ($this->presenter instanceof UI\Presenter) {
			try {
				$this->presenter->forward(":$this->errorPresenter:", $args);
			} catch (AbortException $foo) {
				$this->processRequest($this->presenter->getLastCreatedRequest());
			}
		} else {
			$this->processRequest(new Request($this->errorPresenter, Request::FORWARD, $args));
		}
	}

}
