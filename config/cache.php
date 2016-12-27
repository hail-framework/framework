<?php
return [
	'namespace' => 'gps_data',
	'drivers' => [
		'array' => [],
		'yac' => ['ttl' => 86400],
		'redis' => [
			'ttl' => 7 * 86400
		]
	]
];