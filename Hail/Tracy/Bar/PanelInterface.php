<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Tracy\Bar;

/**
 * Custom output for Debugger.
 */
interface PanelInterface
{

	/**
	 * Renders HTML code for custom tab.
	 * @return string
	 */
	public function getTab();

	/**
	 * Renders HTML code for custom panel.
	 * @return string
	 */
	public function getPanel();

}
