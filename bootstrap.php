<?php
// System Start Time
define('START_TIME', isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true));

// Absolute path to the system folder
define('SYSTEM_PATH', __DIR__ . '/');

// Absolute path to the Hail namespace root folder
define('HAIL_PATH', SYSTEM_PATH . 'Hail/');

// Now timestamp
define('NOW', isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time());

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

date_default_timezone_set(
	Config::get('app.timezone')
);

Debugger::enable(
	Config::get('env.debug') ? Debugger::DEVELOPMENT : Debugger::PRODUCTION
);

Gettext::init(LANG_PATH, LANG_DOMAIN,
	Config::get('app.locale')
);

Router::addRoutes(
	Config::get('route')
);