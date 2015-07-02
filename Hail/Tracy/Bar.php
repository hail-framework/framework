<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Tracy;


/**
 * Debug Bar.
 *
 * @author     David Grudl
 */
class Bar
{
	/** @deprecated */
	public $info = array();

	/** @var Bar\PanelInterface[] */
	private $panels = array();


	/**
	 * Add custom panel.
	 * @param  IBarPanel
	 * @param  string
	 * @return self
	 */
	public function addPanel(Bar\PanelInterface $panel, $id = NULL)
	{
		if ($id === NULL) {
			$c = 0;
			do {
				$id = get_class($panel) . ($c++ ? "-$c" : '');
			} while (isset($this->panels[$id]));
		}
		$this->panels[$id] = $panel;
		return $this;
	}


	/**
	 * Returns panel with given id
	 * @param  string
	 * @return IBarPanel|NULL
	 */
	public function getPanel($id)
	{
		return isset($this->panels[$id]) ? $this->panels[$id] : NULL;
	}


	/**
	 * Renders debug bar.
	 * @return void
	 */
	public function render()
	{
		$obLevel = ob_get_level();
		$panels = array();
		foreach ($this->panels as $id => $panel) {
			$idHtml = preg_replace('#[^a-z0-9]+#i', '-', $id);
			try {
				$tab = (string) $panel->getTab();
				$panelHtml = $tab ? (string) $panel->getPanel() : NULL;
				$panels[] = array('id' => $idHtml, 'tab' => $tab, 'panel' => $panelHtml);

			} catch (\Exception $e) {
				$panels[] = array(
					'id' => "error-$idHtml",
					'tab' => "Error in $id",
					'panel' => '<h1>Error: ' . $id . '</h1><div class="tracy-inner">'
						. nl2br(htmlSpecialChars($e, ENT_IGNORE, 'UTF-8')) . '</div>',
				);
				while (ob_get_level() > $obLevel) { // restore ob-level if broken
					ob_end_clean();
				}
			}
		}

		@session_start();
		$session = & $_SESSION['__NF']['debuggerbar'];
		if (preg_match('#^Location:#im', implode("\n", headers_list()))) {
			$session[] = $panels;
			return;
		}

		foreach (array_reverse((array) $session) as $reqId => $oldpanels) {
			$panels[] = array(
				'tab' => '<span title="Previous request before redirect">previous</span>',
				'panel' => NULL,
				'previous' => TRUE,
			);
			foreach ($oldpanels as $panel) {
				$panel['id'] .= '-' . $reqId;
				$panels[] = $panel;
			}
		}
		$session = NULL;

		require __DIR__ . '/assets/Bar/bar.phtml';
	}

}