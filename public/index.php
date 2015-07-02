<?php
require __DIR__ . '/../bootstrap.php';

$result = Router::dispatch(METHOD, ROUTE_REQUEST);

var_dump($result);

if (!isset($result['error'])) {
} else {
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

echo '<br>', sprintf('%f', microtime(true) - START_TIME);