<?php
return [
	'debug' => true,
	'url' => 'http://localhost/pcp',

	'timezone' => 'Asia/Shanghai',
	'locale' => 'zh_CN',
	'domain' => 'default',

    'autoload' => [
        'Hail' => HAIL_PATH, // Hal Framework Root
    ],

    'alias' => [
        'DI'        => 'Hail\\Facades\\DI',
        'Config'    => 'Hail\\Facades\\Config',
        'Alias'     => 'Hail\\Facades\\Alias',
        'Loader'    => 'Hail\\Facades\\Loader',
	    'Router'    => 'Hail\\Facades\\Router',
	    'Gettext'   => 'Hail\\Facades\\Gettext',
    ],
];