<?php

namespace Hail\Util;

use Hail\Util\Yaml\Exception\ParseException;

define('YAML_EXTENSION_LOADED', extension_loaded('yaml'));

class Yaml
{
	/**
	 * Parses YAML into a PHP value.
	 *
	 *  Usage:
	 *  <code>
	 *   $array = Yaml::parse(file_get_contents('config.yml'));
	 *   print_r($array);
	 *  </code>
	 *
	 * @param string $input A string containing YAML
	 *
	 * @return mixed The YAML converted to a PHP value
	 *
	 * @throws ParseException If the YAML is not valid
	 */
	public static function parse(string $input)
	{
		if (YAML_EXTENSION_LOADED) {
			if (ini_get('yaml.decode_php')) {
				ini_set('yaml.decode_php', 0);
			}

			return yaml_parse($input, 0, $i, [
				'!php/const' => [Yaml\Parser::class, 'constant']
			]);
		}

		return (new Yaml\Parser())->parse($input);
	}

	/**
	 * Dumps a PHP value to a YAML string.
	 *
	 * The dump method, when supplied with an array, will do its best
	 * to convert the array into friendly YAML.
	 *
	 * @param mixed $input  The PHP value
	 * @param int   $inline The level where you switch to inline YAML
	 *
	 * @return string A YAML string representing the original PHP value
	 */
	public static function dump($input, $inline = 2)
	{
		if (YAML_EXTENSION_LOADED) {
			return yaml_emit($input, YAML_UTF8_ENCODING, YAML_LN_BREAK);
		}

		return Yaml\Dumper::emit($input, $inline, 0);
	}
}
