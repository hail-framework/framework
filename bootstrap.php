<?php
use Hail\Loader;

// Absolute path to the application base folder
defined('BASE_PATH') || define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);

if (strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
	$forComposer = __DIR__ . '/vendor/autoload.php';
	if (file_exists($forComposer)) {
		require $forComposer;
	}

	require __DIR__ . '/Hail/Loader.php';
	Loader::register();
}
