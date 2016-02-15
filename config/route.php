<?php
/**
 * '/{controller:[a-z\-]+}/{action:[a-z\-]+}/{param}' => [
 *   'methods' => ['GET', 'POST'],
 *   'controller' => '',
 *   'action' => ''
 * ]
 */
return [
	'Panel' => [
		'/{controller:[a-zA-Z\-]+}/{action:[a-zA-Z\-]+}/{param}',
		'/{controller:[a-zA-Z\-]+}/{action:[a-zA-Z\-]+}',
		'/{controller:[a-zA-Z\-]+}/{param}',
		'/{controller:[a-zA-Z\-]+}',
		'/',
	],
	'Api' => [
		'/{version:v[0-9]+}/{controller:[a-zA-Z\-]+}/{action:[a-zA-Z\-]+}/{param}',
		'/{version:v[0-9]+}/{controller:[a-zA-Z\-]+}/{action:[a-zA-Z\-]+}',
		'/{version:v[0-9]+}/{controller:[a-zA-Z\-]+}/{param}',
		'/{version:v[0-9]+}/{controller:[a-zA-Z\-]+}',
		'/{version:v[0-9]+}',
	],
];