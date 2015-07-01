<?php
require __DIR__ . '/../bootstrap.php';


Router::addRoute(['GET', 'POST'], '/pcp/', function() {
    echo _('Hello World!');
});

$result = Router::dispatch(METHOD, PATH);

if (!isset($result['error'])) {
    $handler = $result['handler'];
    $params = $result['params'];
    call_user_func_array($handler, $params);
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