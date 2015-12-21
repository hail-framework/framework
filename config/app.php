<?php
return [
	'timezone' => 'Asia/Shanghai',
	'locale' => 'zh_CN',
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