<?php
require __DIR__ . '/bootstrap.php';

Hail\Loader::buildMap();
echo 'Autoload Map Builded', "\n";

DI::buildMap();
echo 'DI Map Builded', "\n";

Alias::buildMap();
echo 'Alias Map Builded', "\n";