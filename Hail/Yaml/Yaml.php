<?php

namespace Hail\Yaml;


class Yaml
{
	private static $extension = false;

	public static function init()
	{
		self::$extension = extension_loaded('yaml');
	}

	public static function load(string $file): array
	{
		if (self::$extension) {
			return yaml_parse_file($file);
		}

		return (new Native())->load($file);
	}

	public static function loadString(string $content): array
	{
		if (self::$extension) {
			return yaml_parse($content);
		}

		return (new Native())->loadString($content);
	}

	public static function dump($array): string
	{
		if (self::$extension) {
			return yaml_emit($array, YAML_UTF8_ENCODING, YAML_LN_BREAK);
		}

		return Encoder::process($array);
	}
}

Yaml::init();