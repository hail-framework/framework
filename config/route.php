<?php
/**
 * [
 *   'methods' => ['GET', 'POST'],
 *   'route' => '/{controller:[a-z\-]+}/{action:[a-z\-]+}/{param}',
 *   'controller' => '',
 *   'action' => ''
 * ]
 */
return [
	'Panel' => [
		'/{controller:[a-z\-]+}/{action:[a-z\-]+}/{param}',
		'/{controller:[a-z\-]+}/{action:[a-z\-]+}',
		'/{controller:[a-z\-]+}/{param}',
		'/{controller:[a-z\-]+}',
		'/',
	],
	'Api' => [
		'/{version:v[0-9]+}/{controller:[a-z\-]+}/{action:[a-z\-]+}/{param}',
		'/{version:v[0-9]+}/{controller:[a-z\-]+}/{action:[a-z\-]+}',
		'/{version:v[0-9]+}/{controller:[a-z\-]+}/{param}',
		'/{version:v[0-9]+}/{controller:[a-z\-]+}',
		'/{version:v[0-9]+}',
	],
];