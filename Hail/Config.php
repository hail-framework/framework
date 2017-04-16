<?php

namespace Hail;

use Hail\Util\{
    Arrays, ArrayDot, ArrayTrait, OptimizeTrait, Yaml
};

/**
 * Class Php
 *
 * @package Hail\Config
 */
class Config implements \ArrayAccess
{
	use OptimizeTrait;
	use ArrayTrait;

	/**
	 * All of the configuration items.
	 *
	 * @var ArrayDot
	 */
	protected $items = [];

	public function __construct()
	{
		$this->items = Arrays::dot();
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value)
	{
		// 框架内置 config 不允许修改
		if ($key[0] === '.') {
			return;
		}

		$this->items[$key] = $value;
	}

	/**
	 * Get the specified configuration value.
	 *
	 * @param  string $key
	 *
	 * @return mixed
	 */
	public function get(string $key)
	{
		if (isset($this->items[$key])) {
			return $this->items[$key];
		}

		if ($key === '' || $key === '.') {
			return null;
		}

		$space = static::getSpace($key);

		if (isset($this->items[$space])) {
			return null;
		}

		$this->items[$space] = $this->load($space);

		return $this->items[$key] ?? null;
	}

	public function delete($key)
	{
		unset($this->items[$key]);
	}

	/**
	 * Read config array from cache or file
	 *
	 * 优先 {BASE_PATH}/config/{$space}.*，其次 {HAIL_PATH}/config/{$space}.*
	 * $space 为 . 开头，只读取 {HAIL_PATH}/config/{$space}.*
	 * 扩展名优先 php > yml > yaml
	 *
	 * @param string $space
	 *
	 * @return array|mixed|null
	 */
	protected function load($space): ?array
	{
		$files = static::getFiles($space);

		if ($files === []) {
			return null;
		}

		if (($config = static::optimizeGet($space, $files)) !== false) {
			return $config;
		}

		$config = [];
		foreach ($files as $v) {
			$config += $this->loadFromFile($v);
		}

		static::optimizeSet($space, $config, $files);

		return $config;
	}

	/**
	 * @param string $file from $this->foundFile, already make sure file is exists
	 *
	 * @return array|mixed
	 */
	protected function loadFromFile(?string $file)
	{
		if ($file === null) {
			return [];
		}

		$ext = strrchr($file, '.');
		if ($ext === '.yml' || $ext === '.yaml') {
			return $this->loadYaml($file, $ext);
		}

		return require $file;
	}

	/**
	 * Parse a YAML file or load it from the cache
	 *
	 * @param $file
	 *
	 * @return array|mixed
	 */
	protected function loadYaml($file, $ext)
	{
		$filename = basename($file);
		$dir = RUNTIME_PATH . 'yaml/';

		$cache = $dir . str_replace($ext, '.php', $filename);

		if (@filemtime($cache) < filemtime($file)) {
			$content = Yaml::parseFile($file);

			if (!is_dir($dir) && !@mkdir($dir, 0755) && !is_dir($dir)) {
				throw new \RuntimeException('Temp directory permission denied');
			}

			file_put_contents($cache, '<?php return ' . $this->parseYamlToCode($content) . ';');

			if (function_exists('opcache_invalidate')) {
				opcache_invalidate($cache, true);
			}
		} else {
			$content = require $cache;
		}

		return $content;
	}

	protected function parseYamlToCode(array $array, $level = 0): string
    {
        $pad = '';
        if ($level > 0) {
            $pad = str_repeat("\t", $level);
        }

        $isAssoc = Arrays::isAssoc($array);

        $ret = '[' . "\n";
        foreach ($array as $k => $v) {
            $ret .= $pad . "\t";
            if ($isAssoc) {
                $ret .= var_export($k, true) . ' => ';
            }

            if (is_array($v)) {
                $ret .= $this->parseYamlToCode($v, $level + 1);
            } else {
                $ret .= $this->yamlParseValues($v);
            }

            $ret .= ',' . "\n";
        }

        return $ret . $pad . ']';
    }

    /**
	 * Parse
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	protected function yamlParseValues($value)
	{
	    if ($value instanceof \DateTime) {
	        return var_export($value->format('c'), true);
        }

		if (!is_string($value)) {
			return var_export($value, true);
		}

        if (preg_match('/^%([a-zA-Z_]+)(?::(.*))?%$/', $value, $matches)) {
	        $function = $matches[1];

	        if (!function_exists($function)) {
	            return $value;
            }

            if (!isset($matches[2])) {
	            return $function . '()';
            }

            $args = explode(',', $matches[2]);
	        foreach ($args as &$a) {
	            $a = $this->yamlParseConstant(trim($a));
            }

	        return $function . '(' . implode(', ', $args) . ')';
        }

        return $this->yamlParseConstant($value);
	}

	protected function yamlParseConstant(string $value)
    {
        $value = var_export($value, true);

        preg_match_all('/\${([a-zA-Z_:\\\]+)}/', $value, $matches);

        if (!empty($matches[0])) {
            $replace = [];
            foreach ($matches[0] as $k => $v) {
                $replace[$v] = '\' . \\' . str_replace('\\\\', '\\', $matches[1][$k]) . ' . \'';
            }

            if ($replace !== []) {
                $value = strtr($value, $replace);
            }
        }

        return $value;
    }

    protected static function getSpace(string $key): string
	{
		return $key[0] === '.' ?
			'.' . explode('.', $key)[1] :
			explode('.', $key)[0];
	}

	protected static function getFiles(string $space): array
	{
		$file = 'config/' . $space . '.';

		$baseFile = static::foundFile(HAIL_PATH, $file);
		$files = [];

		if (BASE_PATH !== HAIL_PATH && $space[0] !== '.') {
			$customFile = static::foundFile(BASE_PATH, $file);
			if ($customFile !== null) {
				$files[] = $customFile;
			}
		}

		if ($baseFile !== null) {
			$files[] = $baseFile;
		}

		return $files;
	}

	protected static function foundFile(string $path, string $file): ?string
	{
		foreach (['php', 'yml', 'yaml'] as $ext) {
			$real = $path . $file . $ext;
			if (file_exists($real)) {
				return $real;
			}
		}

		return null;
	}

	public static function filemtime(string $key): ?int
	{
		$files = static::getFiles(
			static::getSpace($key)
		);

		if ($files === []) {
			return null;
		}

		$time = [];
		foreach ($files as $v) {
			$time[] = filemtime($v);
		}

		return max($time);
	}
}