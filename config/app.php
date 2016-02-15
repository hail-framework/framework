<?php
return [
	'timezone' => 'Asia/Shanghai',
	'locale' => 'zh_CN',
	'database' => [
		// required
		'type' => 'mysql',
		'database' => 'games_panel',
		'server' => 'localhost',
		'username' => 'root',
		'password' => 'w12345',
		'charset' => 'utf8mb4',

		// [optional]
		'port' => 3306,

		// [optional] Table prefix
		'prefix' => 'gp_',

		// driver_option for connection, read more from http://www.php.net/manual/en/pdo.setattribute.php
		'option' => [
			PDO::ATTR_CASE => PDO::CASE_NATURAL
		]
	],
	'cache' => [
		'namespace' => 'data',
		'drivers' => [
			'array' => [],
			'apc' => [],
			'file' => [
				'directory' => TEMP_PATH . 'cache/'
			]
		]
	],

	'template' => [
		'directory' => SYSTEM_PATH . 'template/',
		'cache' => TEMP_PATH . 'template/',
	],

	'cross_origin' => 'http://127.0.0.1:3000',
];