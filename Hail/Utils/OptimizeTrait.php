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
				if (!file_exists($file) || filemtime($file) !== $check[1]) {
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
		if ($file !== null && file_exists($file)) {
			$key = [
				$key => $value,
				$key . '|time' => [NOW, filemtime($file)],
			];
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
}