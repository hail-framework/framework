<?php
return [
	'debug' => true,

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
	    'Debugger'  => 'Hail\\Tracy\\Debugger',
    ],
];