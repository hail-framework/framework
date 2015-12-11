<?php
require __DIR__ . '/../bootstrap.php';

$result = Router::dispatch(
	Request::getMethod(),
	Request::getPathInfo()
);

if (isset($result['error'])) {
	switch ($result['error']['code']) {
		case 404 :
			// Not found handler here
			break;
		case 405 :
			// Method not allowed handler here
			$allowedMethods = $result['allowed'];
			if ($method == 'OPTIONS') {
				// OPTIONS method handler here
			}
			break;
	}
}