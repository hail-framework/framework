<?php
require __DIR__ . '/bootstrap.php';

Hail\Bootstrap::init();

if (strpos(__DIR__, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) === false) {
	Hail\Loader::buildMap();
	echo 'Autoload Map Generated', "\n";
} else {
	echo 'Please use "composer dump-autoload --optimize" to optimize autoload performance.', "\n";
}

(new Hail\Container\Compiler())->compile();
echo 'Container Generated', "\n";

$helperDir = __DIR__ . '/helper/';
if (!is_dir($helperDir)) {
	exit;
}

foreach (scandir($helperDir) as $file) {
	if (in_array($file, ['.', '..'], true)) {
		continue;
	}

	unlink($helperDir . $file);
}

foreach (
	[
		['App\\Library', BASE_PATH, function ($class) {
			$ref = new ReflectionClass($class);
			return $ref->isInstantiable();
		}],
		['App\\Model', BASE_PATH, function ($class) {
			$ref = new ReflectionClass($class);
			return $ref->isInstantiable();
		}]
	] as $v
) {
	[$namespace, $root, $check] = $v;

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
				$comment .= ' * @property-read ' . $classFull . ' $' . lcfirst($name) . "\n";
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