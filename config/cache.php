<?php
return [
	'namespace' => 'gps_data',
	'drivers' => [
		'array' => [],
		'yac' => ['lifetime' => 86400],
		'redis' => [
			'lifetime' => 7 * 86400
		]
	]
];