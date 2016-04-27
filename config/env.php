<?php
return [
	'version' => '0.0.1',

	'debug' => true,

	'autoload' => [
		'Hail' => HAIL_PATH, // Hal Framework Root
		'App' => SYSTEM_PATH . 'app/', // App Root
	],

	'alias' => [
		'Debugger' => 'Hail\\Tracy\\Debugger',
	],
];