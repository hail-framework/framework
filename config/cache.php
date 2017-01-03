<?php
return [
	'namespace' => 'gps_data',
	'drivers' => [
		'yac' => ['ttl' => 86400],
		'redis' => [
			'ttl' => 604800 // 7 days
		]
	]
];