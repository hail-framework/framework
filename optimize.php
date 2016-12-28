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


$helperDir = __DIR__ . '/helper/';
foreach (scandir($helperDir) as $file) {
	if (in_array($file, ['.', '..'], true)) {
		continue;
	}

	unlink($helperDir . $file);
}

$alias = include Config::get('.hail.map.alias');
$template = <<<EOD
<?php
class %s extends %s {}
EOD;

foreach ($alias as $k => $v) {
	file_put_contents($helperDir . $k . '.php', sprintf($template, $k, $v));
}
echo 'Alias Class Helper Generated', "\n";


foreach (
	[
		['App\\Library', SYSTEM_PATH, function ($class) {
			$ref = new ReflectionClass($class);
			return $ref->isInstantiable();
		}],
		['App\\Model', SYSTEM_PATH, function ($class) {
			$ref = new ReflectionClass($class);
			return $ref->isInstantiable();
		}]
	] as $v
) {
	list($namespace, $root, $check) = $v;

	$comment = '/**' . "\n";
	$dir = $root . str_replace('\\', '/', $namespace);
	foreach (scandir($dir) as $file) {
		if (in_array($file, ['.', '..'], true) || strrchr($file, '.') !== '.php') {
			continue;
		}

		$name = substr($file, 0, -4);
		$classFull = '\\' . $namespace . '\\' . $name;

		try {

			if ($check($classFull)) {
				$comment .= ' * @property-read ' . $classFull . ' ' . lcfirst($name) . "\n";
			}
		} catch (Exception $e) {
		}
	}
	$comment .= ' */';
	$template = <<<EOD
<?php
%s
class %s {}
EOD;

	$class = substr(strrchr($namespace, '\\'), 1) . 'Factory';
	file_put_contents($helperDir . $class . '.php', sprintf($template, $comment, $class));
}
echo 'Object Factory Helper Generated', "\n";