<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/7/5 0005
 * Time: 13:15
 */

namespace Hail\Tracy\Bar;


/**
 * Session panel for Debugger Bar.
 */
class SessionPanel implements PanelInterface
{

	/**
	 * Renders tab.
	 * @return string
	 */
	public function getTab()
	{
		ob_start();
		require __DIR__ . '/templates/session.tab.phtml';
		return ob_get_clean();
	}


	/**
	 * Renders panel.
	 * @return string
	 */
	public function getPanel()
	{
		ob_start();
		require __DIR__ . '/templates/session.panel.phtml';
		return ob_get_clean();
	}

}
