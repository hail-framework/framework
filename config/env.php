<?php
return [
	'version' => '0.0.1',

	'debug' => true,

	'autoload' => [
		'Hail'  => HAIL_PATH, // Hal Framework Root
		'App'   => SYSTEM_PATH . 'app/', // App Root
	],

	'alias' => [
		'DI'        => 'Hail\\Facades\\DI',
		'Config'    => 'Hail\\Facades\\Config',
		'Loader'    => 'Hail\\Facades\\Loader',
		'Alias'     => 'Hail\\Facades\\Alias',
		'Trace'     => 'Hail\\Facades\\Trace',
		'DB'        => 'Hail\\Facades\\DB',
		'Cache'     => 'Hail\\Facades\\Cache',
		'Request'   => 'Hail\\Facades\\Request',
		'Response'  => 'Hail\\Facades\\Response',
		'Router'    => 'Hail\\Facades\\Router',
		'Gettext'   => 'Hail\\Facades\\Gettext',
		'Console'   => 'Hail\\Facades\\Console',
		'Event'     => 'Hail\\Facades\\Event',
		'App'       => 'Hail\\Facades\\Application',
		'Output'    => 'Hail\\Facades\\Output',
		'Model'     => 'Hail\\Facades\\Model',
		'Lib'       => 'Hail\\Facades\\Library',
		'Debugger'  => 'Hail\\Tracy\\Debugger',
	],

	'output' => 'json',
];