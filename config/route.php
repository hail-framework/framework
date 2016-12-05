<?php
/**
 * '/{controller:[a-z\-]+}/{action:[a-z\-]+}/{param}' => [
 *   'methods' => ['GET', 'POST'],
 *   'controller' => '',
 *   'action' => ''
 * ]
 */
return [
	'Api' => [
		'/{version:v[0-9]+}/{controller:[a-zA-Z0-9]+}/{action:[a-zA-Z0-9]+}/{do}',
		'/{version:v[0-9]+}/{controller:[a-zA-Z0-9]+}/{action:[a-zA-Z0-9]+}',
		'/{version:v[0-9]+}/{controller:[a-zA-Z0-9]+}',
		'/{version:v[0-9]+}',
	],
	'Panel' => [
		'/{controller:[a-zA-Z0-9]+}/{action:[a-zA-Z0-9]+}/{do}',
		'/{controller:[a-zA-Z0-9]+}/{action:[a-zA-Z0-9]+}',
		'/{controller:[a-zA-Z0-9]+}',
		'/',
	],
];