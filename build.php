<?php
require __DIR__ . '/bootstrap.php';

if (strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
	Hail\Loader::buildMap();
	echo 'Autoload Map Generated', "\n";
} else {
	echo 'Please use "composer dump-autoload --optimize" to optimize autoload performance.', "\n";
}

DI::buildMap();
echo 'DI Map Generated', "\n";

Alias::buildMap();
echo 'Alias Map Generated', "\n";

$alias = Alias::getAliases();
$template = <<<EOD
<?php
class %s extends %s {}
EOD;

foreach ($alias as $k => $v) {
	file_put_contents(__DIR__ . '/helper/' . $k . '.php', sprintf($template, $k, $v));
}
echo 'Alias Class Helper Generated', "\n";