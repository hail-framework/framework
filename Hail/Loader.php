<?php
namespace Hail;

defined('STORAGE_PATH') || define('STORAGE_PATH', BASE_PATH . 'storage' . DIRECTORY_SEPARATOR);
defined('RUNTIME_PATH') || define('RUNTIME_PATH', STORAGE_PATH . 'runtime' . DIRECTORY_SEPARATOR);
define('HAIL_PATH', substr(__DIR__, 0, -4));

/**
 * Simple PSR-4 Class Loader for Hail framework
 */
class Loader
{
	protected static $mapFile = RUNTIME_PATH . 'map.autoload.php';
	protected static $classesMap;

	protected static $registered = false;

	/**
	 * An associative array where the key is a namespace prefix and the value
	 * is an array of base directories for classes in that namespace.
	 *
	 * @var array
	 */
	protected static $prefixes = [
		'Hail' => HAIL_PATH . 'Hail/', // Hail Framework Classes
		'App' => BASE_PATH . 'App/', // App Classes
		'Psr' => HAIL_PATH . 'Psr/', // Psr Interface
	];

	/**
	 * Register loader with SPL autoloader stack.
	 *
	 */
	public static function register()
	{
		if (!self::$registered) {
			if (file_exists(self::$mapFile)) {
				self::$classesMap = require self::$mapFile;
			}

			spl_autoload_register([__CLASS__, 'loadClass']);
			self::$registered = true;
		}
	}

	/**
	 * Load the mapped file for a namespace prefix and relative class.
	 *
	 * @param string $class The fully-qualified class name.
	 *
	 * @return string|null The mapped file name on success, or null on failure.
	 */
	public static function findFile($class)
	{
		if ($class[0] === '\\') {
			$class = substr($class, 1);
		}

		if (isset(self::$classesMap[$class])) {
			return self::$classesMap[$class];
		}

		$pos = strpos($class, '\\');
		$prefix = substr($class, 0, $pos);

		$baseDir = self::$prefixes[$prefix] ?? null;
		if ($baseDir === null) {
			return false;
		}

		$path = str_replace('\\', '/', substr($class, $pos + 1));
		$file = $baseDir . $path . '.php';
		if (file_exists($file)) {
			return $file;
		}

		return false;
	}

	public static function buildMap()
	{
		$map = [];
		foreach (self::$prefixes as $prefix => $path) {
			$path = rtrim($path, '/');

			foreach (self::scan($path) as $file) {
				if (
					pathinfo($file, PATHINFO_EXTENSION) !== 'php' ||
					!preg_match('/^[A-Z]/', pathinfo($file, PATHINFO_FILENAME))
				) {
					continue;
				}

				$class = str_replace(
					'/', '\\',
					$prefix . str_replace($path, '', substr($file, 0, -4))
				);

				$map[$class] = realpath($file);
			}
		}

		file_put_contents(
			self::$mapFile,
			'<?php return ' . var_export($map, true) . ';'
		);
	}

	private static function scan($path) {
		if (is_file($path)) {
			yield $path;
		} elseif (is_dir($path) && !is_link($path)) {
			foreach (array_diff(scandir($path), ['.', '..']) as $p) {
				foreach (self::scan($path . '/' . $p) as $p2) {
					yield $p2;
				}
			}
		}
	}

	/**
	 * Loads the class file for a given class name.
	 *
	 * @param string $class The fully-qualified class name.
	 *
	 * @return bool True if the file exists, false if not.
	 */
	public static function loadClass($class)
	{
		$file = self::findFile($class);
		if ($file !== false) {
			require $file;

			return true;
		}

		return false;
	}

}