<?php
// Absolute path to the system folder
defined('SYSTEM_PATH') || define('SYSTEM_PATH', __DIR__ . '/');

if (strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
	$forComposer = __DIR__ . '/vendor/autoload.php';
	if (file_exists($forComposer)) {
		require $forComposer;
	}

	require __DIR__ . '/Hail/Loader.php';
	Hail\Loader::register();
}

Hail\Bootstrap::init();