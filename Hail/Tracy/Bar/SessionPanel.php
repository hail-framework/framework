<?php
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
		ob_start(NULL, 0, PHP_OUTPUT_HANDLER_REMOVABLE);
		require __DIR__ . '/templates/session.tab.phtml';
		return ob_get_clean();
	}


	/**
	 * Renders panel.
	 * @return string
	 */
	public function getPanel()
	{
		ob_start(NULL, 0, PHP_OUTPUT_HANDLER_REMOVABLE);
		require __DIR__ . '/templates/session.panel.phtml';
		return ob_get_clean();
	}

}
