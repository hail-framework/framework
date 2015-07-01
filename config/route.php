<?php
return [
	'editor' => [
		[['GET', 'POST'], '/{controller:^[a-zA-Z]+$}/{action:^[a-zA-Z]+$}/{id:^\d+$}/{param}'],
		[['GET', 'POST'], '/{controller:^[a-zA-Z]+$}/{action:^[a-zA-Z]+$}/{id:^\d+$}'],
		[['GET', 'POST'], '/{controller:^[a-zA-Z]+$}/{action:^[a-zA-Z]+$}/{param}'],
		[['GET', 'POST'], '/{controller:^[a-zA-Z]+$}/{action:^[a-zA-Z]+[/]?$}'],
		[['GET', 'POST'], '/{controller:^[a-zA-Z]+$}/{id:\d+}'],
		[['GET', 'POST'], '/{controller:^[a-zA-Z]+[/]?$}'],
		[['GET', 'POST'], '/'],
	],
];