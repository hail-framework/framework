<?php
// Absolute path to the application base folder
defined('BASE_PATH') || define('BASE_PATH', __DIR__ . DIRECTORY_SEPARATOR);

$forComposer = BASE_PATH . 'vendor/autoload.php';
if (file_exists($forComposer)) {
	require $forComposer;
} else {
	require BASE_PATH . 'Hail/Loader.php';
	Hail\Loader::register();
}
