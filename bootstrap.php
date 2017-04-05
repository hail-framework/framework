<?php
use Hail\{
	Loader,
	Bootstrap
};

// Absolute path to the system folder
defined('BASE_PATH') || define('BASE_PATH', __DIR__ . '/');

if (strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
	$forComposer = __DIR__ . '/vendor/autoload.php';
	if (file_exists($forComposer)) {
		require $forComposer;
	}

	require __DIR__ . '/Hail/Loader.php';
	Loader::register();
}

Bootstrap::init();