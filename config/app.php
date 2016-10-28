<?php
return [
	'timezone' => 'Asia/Shanghai',
	'i18n' => [
		'domain' => 'default',
		'locale' => 'zh_CN',
	],

	'serialize' => 'msgpack',

	'allow_origin' => 'http://127.0.0.1:3000',

	'output' => [
		'Api' => 'json',
		'Panel' => 'template',
	],

];