<?php
namespace Hail\Utils;

defined('HAIL_OPTIMIZE_CHECK_DELAY') || define('HAIL_OPTIMIZE_CHECK_DELAY', 5);

trait OptimizeTrait
{
	protected function optimizeGet($key, $file = null)
	{
		if (HAIL_OPTIMIZE_CHECK_DELAY > 0 && $file !== null) {
			$time = $key . '|time';
			$check = Optimize::get(__CLASS__, $time);
			if ($check !== false && NOW >= ($check[0] + HAIL_OPTIMIZE_CHECK_DELAY)) {
				if ($this->optimizeVerifyMTime($file, $check[1])) {
					return false;
				}

				$check[0] = NOW;
				Optimize::set(__CLASS__, $time, $check);
			}
		}

		return Optimize::get(
			__CLASS__, $key
		);
	}

	protected function optimizeSet($key, $value = null, $file = null)
	{
		if ($file !== null) {
			$mtime = $this->optimizeFileMTime($file);
			if ($mtime !== []) {
				$key = [
					$key => $value,
					$key . '|time' => [NOW, $mtime],
				];
			}
		}

		if (is_array($key)) {
			return Optimize::setMultiple(
				__CLASS__, $key
			);
		}

		return Optimize::set(
			__CLASS__, $key, $value
		);
	}

	protected function optimizeVerifyMTime($file, $check)
	{
		$file = array_unique((array) $file);

		foreach ($file as $v) {
			if (file_exists($v)) {
				if (!isset($check[$v]) || filemtime($v) !== $check[$v]) {
					return true;
				}
			} elseif (isset($check[$v])) {
				return true;
			}
		}

		return false;
	}

	protected function optimizeFileMTime($file)
	{
		$file = array_unique((array) $file);

		$mtime = [];
		foreach ($file as $v) {
			if (file_exists($v)) {
				$mtime[$v] = filemtime($v);
			}
		}

		return $mtime;
	}
}