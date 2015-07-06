<?php
return [
	'version' => '0.0.1',

	'debug' => true,

    'autoload' => [
        'Hail' => HAIL_PATH, // Hal Framework Root
    ],

    'alias' => [
        'DI'        => 'Hail\\Facades\\DI',
        'Config'    => 'Hail\\Facades\\Config',
	    'Loader'    => 'Hail\\Facades\\Loader',
        'Alias'     => 'Hail\\Facades\\Alias',
	    'Trace'     => 'Hail\\Facades\\Trace',
	    'Request'   => 'Hail\\Facades\\Request',
	    'Response'  => 'Hail\\Facades\\Response',
	    'Router'    => 'Hail\\Facades\\Router',
	    'Gettext'   => 'Hail\\Facades\\Gettext',
	    'Debugger'  => 'Hail\\Tracy\\Debugger',
	    'Console'   => 'Hail\\Facades\\Console',
    ],
];