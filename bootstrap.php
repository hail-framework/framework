<?php
// System Start Time
define('START_TIME', isset($_SERVER['REQUEST_TIME_FLOAT']) ? $_SERVER['REQUEST_TIME_FLOAT'] : microtime(true));

// Now timestamp
define('NOW', isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time());

// Absolute path to the system folder
!defined('SYSTEM_PATH') && define('SYSTEM_PATH', __DIR__ . '/');

// Path to the Hail namespace root folder
define('HAIL_PATH', SYSTEM_PATH . 'Hail/');

// Absolute path to the temp folder
define('TEMP_PATH', SYSTEM_PATH . 'temp/');

// Embedded cache engine: 'auto', 'apcu', 'xcache', 'yac', 'pcache', 'wincache', 'none'
define('EMBEDDED_CACHE_ENGINE', 'auto');
define('EMBEDDED_CACHE_CHECK_DELAY', 5);

// Language path
define('LANG_PATH', SYSTEM_PATH . 'lang/');
define('LANG_DOMAIN', 'default');

require HAIL_PATH . 'Bootstrap.php';
$di = Hail\Bootstrap::di();
Hail\Bootstrap::autoload($di);

date_default_timezone_set(
	Config::get('app.timezone')
);

if (PHP_SAPI !== 'cli' && Config::get('env.debug')) {
	DI::set('trace', function ($c) {
		return new Hail\Tracy\Bar\TracePanel(
			TEMP_PATH . 'xdebugTrace.xt'
		);
	});
	$debugMode = Debugger::DEVELOPMENT;
} else {
	$debugMode = Debugger::PRODUCTION;
}

Debugger::enable(
	$debugMode,
	TEMP_PATH . 'log/'
);

Gettext::init(LANG_PATH, LANG_DOMAIN,
	Config::get('app.locale')
);

Router::addRoutes(
	Config::get('route')
);