<?php
return [
	'Editor' => [
		[['GET', 'POST'], '/{controller:^[a-z\-]+$}/{action:^[a-z\-]+$}/{id:^\d+$}/{param}'],
		[['GET', 'POST'], '/{controller:^[a-z\-]+$}/{action:^[a-z\-]+$}/{id:^\d+$}'],
		[['GET', 'POST'], '/{controller:^[a-z\-]+$}/{action:^[a-z\-]+$}/{param}'],
		[['GET', 'POST'], '/{controller:^[a-z\-]+$}/{action:^[a-z\-]+$}'],
		[['GET', 'POST'], '/{controller:^[a-z\-]+$}/{id:^\d+$}'],
		[['GET', 'POST'], '/{controller:^[a-z\-]+$}'],
		[['GET', 'POST'], '/'],
	],
];