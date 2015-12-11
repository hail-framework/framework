<?php
namespace Hail\Tracy\Bar;

use Hail\Tracy\Dumper;

/**
 * Routing debugger for Debug Bar.
 */
class RoutePanel implements PanelInterface
{
	/** @var array */
	private $router = [];

	public function __construct()
	{
	}

	/**
	 * Renders tab.
	 * @return string
	 */
	public function getTab()
	{
		$this->getRouteResult();
		ob_start();
		$title = $this->router['title'];
		require __DIR__ . '/templates/route.tab.phtml';
		return ob_get_clean();
	}

	/**
	 * Renders panel.
	 * @return string
	 */
	public function getPanel()
	{
		ob_start();
		$router = $this->router;
		$url = (string) \Request::getUrl();
		$sorted = array(
			'matched', 'url', 'error', 'allowed', 'route', 'params', 'handler'
		);

		require __DIR__ . '/templates/route.panel.phtml';
		return ob_get_clean();
	}

	private function getRouteResult()
	{
		$result = \Router::getResult();
		if (isset($result['error'])) {
			if ($result['error']['code'] === 404) {
				$result['matched'] = 'no';
				$result['title'] =  'Not Found';
			} else {
				$result['matched'] = 'may';
				$result['title'] =  'Not Allowed';
			}
		} else {
			$result['matched'] = 'yes';
			$result['title'] = 'Matched';
		}
		$this->router = $result;
	}

	private function dump($value, $br = false)
	{
		return is_string($value) ?
			htmlSpecialChars($value, ENT_IGNORE, 'UTF-8') . ($br ? '<br />' : '') :
			Dumper::toHtml($value, [Dumper::COLLAPSE => TRUE, Dumper::LIVE => TRUE]);
	}
}
