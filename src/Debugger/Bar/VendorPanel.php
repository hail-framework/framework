<?php

namespace Hail\Debugger\Bar;

use Hail\Util\Exception\JsonException;
use Hail\Util\Json;

/**
 * Bar panel for Tracy (https://tracy.nette.org/) shows versions of libraries parsed from composer.lock.
 *
 * @licence  MIT
 * @link     https://github.com/milo/vendor-versions
 * @author   Hao Feng <flyinghail@msn.com>
 */
class VendorPanel implements PanelInterface
{
	/** @var string */
	private $error;

	/** @var string */
	private $dir;

	public function __construct()
	{
		if (!\is_file(\base_path('composer.lock'))) {
			$this->error = 'Base directory does not contain the composer.lock file.';
		}

		$this->dir = \base_path();
	}

	/**
	 * @return string
	 */
	public function getTab()
	{
		\ob_start();
		require __DIR__ . '/templates/vendor.tab.phtml';

		return \ob_get_clean();
	}

	/**
	 * @return string
	 */
	public function getPanel()
	{
        $jsonFile = $this->dir . DIRECTORY_SEPARATOR . 'composer.json';
        $lockFile = $this->dir . DIRECTORY_SEPARATOR . 'composer.lock';
        $required = $this->decode($jsonFile);
        $installed = $this->decode($lockFile);
        if ($this->error === null) {
            $required = $required ? \array_filter($required) : [];
            $installed = $installed ? \array_filter($installed) : [];
            $required += ['require' => [], 'require-dev' => []];
            $installed += ['packages' => [], 'packages-dev' => []];
            $data = [
                'Packages' => self::format($installed['packages'], $required['require']),
                'Dev Packages' => self::format($installed['packages-dev'], $required['require-dev']),
            ];
        }
        $error = $this->error;

		\ob_start();
		require __DIR__ . '/templates/vendor.panel.phtml';

		return \ob_get_clean();
	}

	/**
	 * @param  array $packages
	 * @param  array $required
	 *
	 * @return array
	 */
	private static function format(array $packages, array $required)
	{
		$data = [];
		foreach ($packages as $p) {
			$data[$p['name']] = (object) [
				'installed' => $p['version'] . ($p['version'] === 'dev-master'
						? (' #' . \substr($p['source']['reference'], 0, 7))
						: ''
					),
				'required' => isset($required[$p['name']])
					? $required[$p['name']]
					: null,
				'url' => isset($p['source']['url'])
					? \preg_replace('/\.git$/', '', $p['source']['url'])
					: null,
			];
		}
		\ksort($data);

		return $data;
	}

	/**
	 * @param  string $file
	 *
	 * @return array|NULL
	 */
	private function decode($file)
	{
		$json = @\file_get_contents($file);
		if ($json === false) {
			$this->error = $this->error ?: \error_get_last()['message'];

			return null;
		}

		try {
			return Json::decode($json);
		} catch (JsonException $e) {
			$this->error = $this->error ?: $e->getMessage();

			return null;
		}
	}
}
