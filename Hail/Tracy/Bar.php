<?php
/**
 * This file is part of the Tracy (https://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Tracy;

use Hail\Facades\Strings;

/**
 * Debug Bar.
 */
class Bar
{
	/** @var Bar\PanelInterface[] */
	private $panels = [];

	/** @var bool */
	private $dispatched;

	/**
	 * Add custom panel.
	 *
	 * @param  Bar\PanelInterface
	 * @param  string
	 *
	 * @return self
	 */
	public function addPanel(Bar\PanelInterface $panel, $id = null)
	{
		if ($id === null) {
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
	 *
	 * @param  string
	 *
	 * @return Bar\PanelInterface|NULL
	 */
	public function getPanel($id)
	{
		return $this->panels[$id] ?? null;
	}


	/**
	 * Renders debug bar.
	 *
	 * @return void
	 */
	public function render()
	{
		$useSession = $this->dispatched && session_status() === PHP_SESSION_ACTIVE;
		$redirectQueue = &$_SESSION['_tracy']['redirect'];

		if (!Helpers::isHtmlMode() && !Helpers::isAjax()) {
			return;

		} elseif (Helpers::isAjax()) {
			$rows[] = (object) ['type' => 'ajax', 'panels' => $this->renderPanels('-ajax')];
			$dumps = Dumper::fetchLiveData();
			$contentId = $useSession ? $_SERVER['HTTP_X_TRACY_AJAX'] . '-ajax' : null;

		} elseif (preg_match('#^Location:#im', implode("\n", headers_list()))) { // redirect
			$redirectQueue = array_slice((array) $redirectQueue, -10);
			Dumper::fetchLiveData();
			Dumper::$livePrefix = count($redirectQueue) . 'p';
			$redirectQueue[] = [
				'panels' => $this->renderPanels('-r' . count($redirectQueue)),
				'dumps' => Dumper::fetchLiveData(),
			];

			return;

		} else {
			$rows[] = (object) ['type' => 'main', 'panels' => $this->renderPanels()];
			$dumps = Dumper::fetchLiveData();
			foreach (array_reverse((array) $redirectQueue) as $info) {
				$rows[] = (object) ['type' => 'redirect', 'panels' => $info['panels']];
				$dumps += $info['dumps'];
			}
			$redirectQueue = null;
			$contentId = $useSession ? substr(md5(uniqid('', true)), 0, 10) : null;
		}

		ob_start(function () {
		});
		require __DIR__ . '/assets/Bar/panels.phtml';
		require __DIR__ . '/assets/Bar/bar.phtml';
		$content = Strings::fixEncoding(ob_get_clean());

		if ($contentId) {
			$queue = &$_SESSION['_tracy']['bar'];
			$queue = array_slice(array_filter((array) $queue), -5, null, true);
			$queue[$contentId] = ['content' => $content, 'dumps' => $dumps];
		}

		if (Helpers::isHtmlMode()) {
			$baseUrl = extension_loaded('xdebug') ? '?XDEBUG_SESSION_STOP=1&' : '?';
			require __DIR__ . '/assets/Bar/loader.phtml';
		}
	}


	/**
	 * @return array
	 */
	private function renderPanels($suffix = null)
	{
		set_error_handler(function ($severity, $message, $file, $line) {
			if (error_reporting() & $severity) {
				throw new \ErrorException($message, 0, $severity, $file, $line);
			}
		});

		$obLevel = ob_get_level();
		$panels = [];

		foreach ($this->panels as $id => $panel) {
			$idHtml = preg_replace('#[^a-z0-9]+#i', '-', $id) . $suffix;
			try {
				$tab = (string) $panel->getTab();
				$panelHtml = $tab ? (string) $panel->getPanel() : null;

			} catch (\Throwable $e) {
			} catch (\Exception $e) {
			}
			if (isset($e)) {
				while (ob_get_level() > $obLevel) { // restore ob-level if broken
					ob_end_clean();
				}
				$idHtml = "error-$idHtml";
				$tab = "Error in $id";
				$panelHtml = "<h1>Error: $id</h1><div class='tracy-inner'>" . nl2br(Helpers::escapeHtml($e)) . '</div>';
				unset($e);
			}
			$panels[] = (object) ['id' => $idHtml, 'tab' => $tab, 'panel' => $panelHtml];
		}

		restore_error_handler();

		return $panels;
	}


	/**
	 * Renders debug bar assets.
	 *
	 * @return bool
	 */
	public function dispatchAssets()
	{
		if (isset($_GET['_tracy_bar']) && $_GET['_tracy_bar'] === 'assets') {
			header('Content-Type: text/javascript');
			header('Cache-Control: max-age=864000');
			header_remove('Pragma');
			header_remove('Set-Cookie');
			$css = file_get_contents(__DIR__ . '/assets/Bar/bar.css')
				. file_get_contents(__DIR__ . '/assets/Toggle/toggle.css')
				. file_get_contents(__DIR__ . '/assets/Dumper/dumper.css')
				. file_get_contents(__DIR__ . '/assets/BlueScreen/bluescreen.css');
			$js = file_get_contents(__DIR__ . '/assets/Bar/bar.js')
				. file_get_contents(__DIR__ . '/assets/Toggle/toggle.js')
				. file_get_contents(__DIR__ . '/assets/Dumper/dumper.js')
				. file_get_contents(__DIR__ . '/assets/BlueScreen/bluescreen.js');
			echo 'localStorage.setItem("tracy-style", ' . json_encode($css) . ');';
			echo 'localStorage.setItem("tracy-script", ' . json_encode($js) . ');';
			echo 'localStorage.setItem("tracy-version", ' . json_encode(Debugger::VERSION) . ');';

			return true;
		}
	}


	/**
	 * Renders debug bar content.
	 *
	 * @return bool
	 */
	public function dispatchContent()
	{
		$this->dispatched = true;
		if (Helpers::isAjax()) {
			header('X-Tracy-Ajax: 1'); // session must be already locked
		}

		if (preg_match('#^content(-ajax)?.(\w+)$#', isset($_GET['_tracy_bar']) ? $_GET['_tracy_bar'] : '', $m)) {
			$session = &$_SESSION['_tracy']['bar'][$m[2] . $m[1]];
			header('Content-Type: text/javascript');
			header('Cache-Control: max-age=60');
			header_remove('Set-Cookie');
			if ($session) {
				$method = $m[1] ? 'loadAjax' : 'init';
				echo "Tracy.Debug.$method(", json_encode($session['content']), ', ', json_encode($session['dumps']), ');';
				$session = null;
			}
			$session = &$_SESSION['_tracy']['bluescreen'][$m[2]];
			if ($session) {
				echo "Tracy.BlueScreen.loadAjax(", json_encode($session['content']), ', ', json_encode($session['dumps']), ');';
				$session = null;
			}

			return true;
		}
	}

}
