<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Util\Yaml;

use Hail\Util\Yaml\Exception\DumpException;

/**
 * Dumper dumps PHP variables to YAML strings.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Dumper
{
	// Mapping arrays for escaping a double quoted string. The backslash is
	// first to ensure proper escaping because str_replace operates iteratively
	// on the input arrays. This ordering of the characters avoids the use of strtr,
	// which performs more slowly.
	private static $escapees = ['\\', '\\\\', '\\"', '"',
		"\x00", "\x01", "\x02", "\x03", "\x04", "\x05", "\x06", "\x07",
		"\x08", "\x09", "\x0a", "\x0b", "\x0c", "\x0d", "\x0e", "\x0f",
		"\x10", "\x11", "\x12", "\x13", "\x14", "\x15", "\x16", "\x17",
		"\x18", "\x19", "\x1a", "\x1b", "\x1c", "\x1d", "\x1e", "\x1f",
		"\xc2\x85", "\xc2\xa0", "\xe2\x80\xa8", "\xe2\x80\xa9"];
	private static $escaped = ['\\\\', '\\"', '\\\\', '\\"',
		'\\0', '\\x01', '\\x02', '\\x03', '\\x04', '\\x05', '\\x06', '\\a',
		'\\b', '\\t', '\\n', '\\v', '\\f', '\\r', '\\x0e', '\\x0f',
		'\\x10', '\\x11', '\\x12', '\\x13', '\\x14', '\\x15', '\\x16', '\\x17',
		'\\x18', '\\x19', '\\x1a', '\\e', '\\x1c', '\\x1d', '\\x1e', '\\x1f',
		'\\N', '\\_', '\\L', '\\P'];

	/**
	 * The amount of spaces to use for indentation of nested nodes.
	 *
	 * @var int
	 */
	protected static $indentation = 2;

	/**
	 * Dumps a PHP value to YAML.
	 *
	 * @param mixed $input  The PHP value
	 * @param int   $inline The level where you switch to inline YAML
	 * @param int   $indent The level of indentation (used internally)
	 *
	 * @return string The YAML representation of the PHP value
	 */
	public static function emit($input, $inline = 0, $indent = 0)
	{
		$output = '';
		$prefix = $indent ? str_repeat(' ', $indent) : '';

		if ($inline <= 0 || !is_array($input) || empty($input)) {
			$output .= $prefix . self::dump($input);
		} else {
			$isAHash = self::isHash($input);

			foreach ($input as $key => $value) {
				if ($inline >= 1 && is_string($value) && false !== strpos($value, "\n")) {
					$output .= sprintf("%s%s%s |\n", $prefix, $isAHash ? self::dump($key) . ':' : '-', '');

					foreach (preg_split('/\n|\r\n/', $value) as $row) {
						$output .= sprintf("%s%s%s\n", $prefix, str_repeat(' ', self::$indentation), $row);
					}

					continue;
				}

				$willBeInlined = $inline - 1 <= 0 || !is_array($value) || empty($value);

				$output .= sprintf('%s%s%s%s',
						$prefix,
						$isAHash ? self::dump($key) . ':' : '-',
						$willBeInlined ? ' ' : "\n",
						self::emit($value, $inline - 1, $willBeInlined ? 0 : $indent + self::$indentation)
					) . ($willBeInlined ? "\n" : '');
			}
		}

		return $output;
	}

	/**
	 * Dumps a given PHP variable to a YAML string.
	 *
	 * @param mixed $value The PHP variable to convert
	 *
	 * @return string The YAML string representing the PHP value
	 *
	 * @throws DumpException When trying to dump PHP resource
	 */
	private static function dump($value)
	{
		switch (true) {
			case is_resource($value):
				throw new DumpException(sprintf('Unable to dump PHP resources in a YAML file ("%s").', get_resource_type($value)));

			case $value instanceof \DateTimeInterface:
				return $value->format('c');
			case is_object($value):
				if ($value instanceof \stdClass || $value instanceof \ArrayObject) {
					return self::dumpArray((array) $value);
				}

				return '!php/object ' . serialize($value);

			case is_array($value):
				return self::dumpArray($value);
			case null === $value:
				return 'null';
			case true === $value:
				return 'true';
			case false === $value:
				return 'false';
			case ctype_digit($value):
				return is_string($value) ? "'$value'" : (int) $value;
			case is_numeric($value):
				$locale = setlocale(LC_NUMERIC, 0);
				if (false !== $locale) {
					setlocale(LC_NUMERIC, 'C');
				}
				if (is_float($value)) {
					$repr = (string) $value;
					if (is_infinite($value)) {
						$repr = str_ireplace('INF', '.Inf', $repr);
					} elseif (floor($value) == $value && $repr == $value) {
						// Preserve float data type since storing a whole number will result in integer value.
						$repr = '!!float ' . $repr;
					}
				} else {
					$repr = is_string($value) ? "'$value'" : (string) $value;
				}
				if (false !== $locale) {
					setlocale(LC_NUMERIC, $locale);
				}

				return $repr;
			case '' === $value:
				return "''";
			case self::isBinaryString($value):
				return '!!binary ' . base64_encode($value);
			case self::requiresDoubleQuoting($value):
				return self::escapeWithDoubleQuotes($value);
			case self::requiresSingleQuoting($value):
			case Parser::preg_match('{^[0-9]+[_0-9]*$}', $value):
			case Parser::preg_match('~^0x[0-9a-f_]++$~i', $value):
			case Parser::preg_match(Inline::getTimestampRegex(), $value):
				return self::escapeWithSingleQuotes($value);
			default:
				return $value;
		}
	}

	/**
	 * Check if given array is hash or just normal indexed array.
	 *
	 * @param array $value The PHP array to check
	 *
	 * @return bool true if value is hash array, false otherwise
	 */
	private static function isHash(array $value)
	{
		$expectedKey = 0;

		foreach ($value as $key => $val) {
			if ($key !== $expectedKey++) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Dumps a PHP array to a YAML string.
	 *
	 * @param array $value The PHP array to dump
	 *
	 * @return string The YAML string representing the PHP array
	 */
	private static function dumpArray($value)
	{
		// array
		if (!self::isHash($value)) {
			$output = [];
			foreach ($value as $val) {
				$output[] = self::dump($val);
			}

			return sprintf('[%s]', implode(', ', $output));
		}

		// hash
		$output = [];
		foreach ($value as $key => $val) {
			$output[] = sprintf('%s: %s', self::dump($key), self::dump($val));
		}

		return sprintf('{ %s }', implode(', ', $output));
	}

	private static function isBinaryString($value)
	{
		return !preg_match('//u', $value) || preg_match('/[^\x00\x07-\x0d\x1B\x20-\xff]/', $value);
	}

	/**
	 * Determines if a PHP value would require double quoting in YAML.
	 *
	 * @param string $value A PHP value
	 *
	 * @return bool True if the value would require double quotes
	 */
	private static function requiresDoubleQuoting($value)
	{
		return preg_match("/[\\x00-\\x1f]|\xc2\x85|\xc2\xa0|\xe2\x80\xa8|\xe2\x80\xa9/u", $value);
	}

	/**
	 * Escapes and surrounds a PHP value with double quotes.
	 *
	 * @param string $value A PHP value
	 *
	 * @return string The quoted, escaped string
	 */
	private static function escapeWithDoubleQuotes($value)
	{
		return '"' . str_replace(self::$escapees, self::$escaped, $value). '"';
	}

	/**
	 * Determines if a PHP value would require single quoting in YAML.
	 *
	 * @param string $value A PHP value
	 *
	 * @return bool True if the value would require single quotes
	 */
	private static function requiresSingleQuoting($value)
	{
		// Determines if a PHP value is entirely composed of a value that would
		// require single quoting in YAML.
		if (in_array(strtolower($value), ['null', '~', 'true', 'false', 'y', 'n', 'yes', 'no', 'on', 'off'], true)) {
			return true;
		}

		// Determines if the PHP value contains any single characters that would
		// cause it to require single quoting in YAML.
		return preg_match('/[ \s \' " \: \{ \} \[ \] , & \* \# \?] | \A[ \- ? | < > = ! % @ ` ]/x', $value);
	}

	/**
	 * Escapes and surrounds a PHP value with single quotes.
	 *
	 * @param string $value A PHP value
	 *
	 * @return string The quoted, escaped string
	 */
	private static function escapeWithSingleQuotes($value)
	{
		return '\'' . str_replace('\'', '\'\'', $value) . '\'';
	}
}
