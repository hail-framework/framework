<?php
return [
	'timezone' => 'Asia/Shanghai',
	'locale' => 'zh_CN',
	'database' => [
		// required
		'database_type' => 'mysql',
		'database_name' => 'admin_panel',
		'server' => '192.168.1.179',
		'username' => 'root',
		'password' => 'w12345',
		'charset' => 'utf8mb4',

		// [optional]
		'port' => 3306,

		// [optional] Table prefix
		'prefix' => 'ap_',

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
];