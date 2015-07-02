<?php
// System Start Time
define('START_TIME', isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true));

// Absolute path to the system folder
define('SYSTEM_PATH', __DIR__ . '/');

// Absolute path to the Hail namespace root folder
define('HAIL_PATH', SYSTEM_PATH . 'Hail/');

// Now timestamp
define('NOW', isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time());

// Is this an AJAX request?
define('AJAX_REQUEST', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// The current TLD address, scheme, and port
define('DOMAIN', (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on' ? 'https' : 'http') . '://'
	. $_SERVER['HTTP_HOST'] . (($p = $_SERVER['SERVER_PORT']) != 80 AND $p != 443 ? ":$p" : ''));

define('METHOD', isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');

// The current site path
define('REQUEST_URI', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Embedded cache engine: 'auto', 'apcu', 'apc', 'xcache', 'yac', 'pcache', 'wincache', 'none'
define('EMBEDDED_CACHE_ENGINE', 'auto');
define('EMBEDDED_CACHE_CHECK_DELAY', 5);

// Config Setting
define('CONFIG_ENABLE_YACONF', false);
define('CONFIG_PATH', SYSTEM_PATH . 'config/');

// Language path
define('LANG_PATH', SYSTEM_PATH . 'lang/');
define('LANG_DOMAIN', 'default');

$di = require HAIL_PATH . 'Bootstrap.php';
$di = Hail\Bootstrap::di();
Hail\Bootstrap::autoload($di);

Debugger::enable(
	Config::get('env.debug') ? Debugger::DEVELOPMENT : Debugger::PRODUCTION
);

define('ROUTE_REQUEST', Hail\Bootstrap::path());

date_default_timezone_set(
	Config::get('app.timezone')
);

Gettext::init(LANG_PATH, LANG_DOMAIN,
	Config::get('app.locale')
);

Router::addRoutes(
	Config::get('route')
);