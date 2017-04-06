<?php

namespace Hail\Container;

use Hail\Config;

/**
 * This class implements a simple dependency injection container.
 */
class Compiler
{
	protected static $file = RUNTIME_PATH . 'Container.php';

	protected $config;

	protected $points = [];
	protected $methods = [];

	public function __construct()
	{
		$config = new Config();

		$this->config = $config->get('container');
	}


	public function compile()
	{
		$this->buildParameters();
		$this->buildServices();

		$code = "<?php\n";
		$code .= "class Container extends Hail\\Container\\Container\n";
		$code .= "{\n";

		$code .= "\tprotected static \$entryPoints = [\n";
		foreach ($this->points as $k => $v) {
			$code .= "\t\t" . $this->classname($k) . " => $v,\n";
		}
		$code .= "\t];\n\n";
		$code .= "\tpublic function get(\$name)\n";
		$code .= "\t{\n";
		$code .= "\t\tif (isset(\$this->active[\$name])) {\n";
		$code .= "\t\t\treturn \$this->values[\$name];\n";
		$code .= "\t\t}\n\n";
		$code .= "\t\tif (isset(static::\$entryPoints[\$name])) {\n";
		$code .= "\t\t\treturn \$this->{static::\$entryPoints[\$name]}();\n";
		$code .= "\t\t}\n\n";
		$code .= "\t\treturn parent::get(\$name);\n";
		$code .= "\t}\n\n";
		$code .= implode("\n\n", $this->methods);
		$code .= '}';

		file_put_contents(static::$file, $code);
	}

	protected function buildParameters()
	{
		$parameters = $this->config['parameters'] ?? [];

		foreach ($parameters as $k => $v) {
			$this->points[$k] = $this->getName($k);
			$this->toCode($k, $this->getStr($v));
		}
	}

	protected function buildServices()
	{
		$services = $this->config['services'] ?? [];

		foreach ($services as $k => $v) {
			if (is_string($v) && $v[0] === '@') {
				$this->points[$k] = $this->getName($k);
				$this->toCode($k, $this->getStr($v));
			}

			if (!is_array($v)) {
				continue;
			}

			$arguments = '';
			if (isset($v['arguments'])) {
				$arguments = $this->getArguments($v['arguments']);
			}

			$suffix = array_merge(
				$this->getProperty($v['property'] ?? []),
				$this->getCalls($v['calls'] ?? [])
			);

			$class = $v['class'] ?? $k;

			if ($k !== $class) {
				$this->points[$k] = $this->getName($k);
				$this->toCode($k, $this->getRef($class));
			}
			$this->points[$class] = $this->getName($class);

			if (isset($v['factory'])) {
				if (is_array($v['factory'])) {
					[$class, $method] = $v['factory'];
					$this->toCode($class, "{$class}::{$method}($arguments)", $suffix);
				} elseif (is_string($v['factory'])) {
					if (strpos($v['factory'], '::') !== false) {
						$this->toCode($class, "{$v['factory']}($arguments)", $suffix);
					} elseif (strpos($v['factory'], ':') !== false) {
						[$ref, $method] = explode(':', $v['factory']);
						$this->toCode($class, $this->getRef($ref) . "->{$method}($arguments)", $suffix);
					}
				}
			} else {
				$this->toCode($class, "new {$class}($arguments)", $suffix);
			}
		}
	}

	protected function getArguments(array $args): string
	{
		return implode(', ', array_map([$this, 'getStr'], $args));
	}

	protected function getProperty(array $props): array
	{
		if ($props === []) {
			return [];
		}

		$return = [];
		foreach ($props as $k => $v) {
			$return[] = $k . ' = ' . $this->getStr($v);
		}

		return $return;
	}

	protected function getCalls(array $calls): array
	{
		if ($calls === []) {
			return [];
		}

		$return = [];
		foreach ($calls as $v) {
			[$method, $args] = $v;
			$args = $this->getArguments($args);
			$return[] = $method . '(' . $args . ')';
		}

		return $return;
	}

	protected function getName($string)
	{
		if ($string[0] === '\\') {
			$string = ltrim($string, '\\');
		} elseif (strpos($string, '\\') === false && strtoupper($string[0]) !== $string[0]) {
			return '\'HAILPARAM__' . str_replace('.', '__', $string) . '\'';
		}

		return '\'HAIL__' . str_replace(['\\', '.'], '__', $string) . '\'';
	}

	protected function getStr($str)
	{
		if (is_string($str)) {
			if (strpos($str, 'CONFIG.') === 0) {
				$str = var_export(substr($str, 7), true);

				return $this->getRef('config') . '->get(' . $str . ')';
			}

			if (isset($str[0]) && $str[0] === '@') {
				$str = substr($str, 1);
				if ($str === '') {
					$str = '@';
				} elseif ($str[0] !== '@') {
					return $this->getRef($str);
				}
			}
		}

		return var_export($str, true);
	}

	protected function getRef($name)
	{
		return '$this->get(' . $this->classname($name) . ')';
	}

	protected function toCode($name, $return, array $suffix = [])
	{
		$function = substr($this->getName($name), 1, -1);

		$name = $this->classname($name);

		$code = "\tprotected function {$function}() {\n";
		if ($suffix !== []) {
			$code .= "\t\t\$object = $return;\n";
			$code .= implode(";\n\t\t\$objcet->", $suffix) . ";\n";
			$return = '$object';
		}

		$code .= "\t\t\$this->active[$name] = true;\n";
		$code .= "\t\treturn \$this->values[$name] = $return;\n";
		$code .= "\t}";

		$this->methods[] = $code;
	}

	protected function classname($name)
	{
		if ($name[0] === '\\') {
			$name = ltrim($name, '\\');
		}

		if (strtoupper($name[0]) === $name[0]) {
			return "$name::class";
		}

		return var_export($name, true);
	}
}
