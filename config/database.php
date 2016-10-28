<?php
return [
	// required
	'type' => 'mysql',
	'database' => 'database',
	'server' => 'localhost',
	'username' => 'root',
	'password' => '',
	'charset' => 'utf8mb4',

	// [optional]
	'port' => 3306,

	// [optional] Table prefix
	'prefix' => 'gp_',

	// driver_option for connection, read more from http://www.php.net/manual/en/pdo.setattribute.php
	'option' => [
		PDO::ATTR_CASE => PDO::CASE_NATURAL
	],

	'extConnectPool' => false,
];