<?php
define('START_TIME', microtime(true));

define('RP', '../');
define('RD', realpath(RP));

require RD . '/Hail/Loader.php';

$loader = new \Hail\Loader();
$loader->addPrefix('Hail', __DIR__ . '/Hail');
$loader->register();