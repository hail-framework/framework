<?php
// System Start Time
define('START_TIME', microtime(true));

// Absolute path to the system folder
define('SYSTEM_PATH', __DIR__ . '/');

// Absolute path to the Hail namespace root folder
define('HAIL_PATH', SYSTEM_PATH . 'Hail/');

require HAIL_PATH . 'Bootstrap.php';

use Hail\Bootstrap;

Bootstrap::constant();
$di = Bootstrap::di();
Bootstrap::loader($di);
Bootstrap::locale();