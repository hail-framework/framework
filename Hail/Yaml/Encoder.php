<?php

namespace Hail\Yaml;

use Hail\Util\Arrays;

class Encoder
{
	const EMPTY = "\0\0\0\0\0";
	const INDENT = 2;

	private static $dumpWordWrap;

	/**
	 * Dump PHP array to YAML
	 *
	 * The dump method, when supplied with an array, will do its best
	 * to convert the array into friendly YAML.  Pretty simple.  Feel free to
	 * save the returned string as tasteful.yaml and pass it around.
	 *
	 * Oh, and you can decide how big the indent is and what the wordwrap
	 * for folding is.  Pretty cool -- just pass in 'false' for either if
	 * you want to use the default.
	 *
	 * Indent's default is 2 spaces, wordwrap's default is 40 characters.  And
	 * you can turn off wordwrap by passing in 0.
	 *
	 * @param array $array    PHP array
	 * @param int   $wordwrap Pass in 0 for no wordwrap
	 * @param bool  $noOpenDashes
	 *
	 * @return string
	 */
	public static function process(array $array, int $wordwrap = 40, bool $noOpenDashes = false): string
	{
		// New features and options.
		self::$dumpWordWrap = $wordwrap;

		// New YAML document
		$string = $noOpenDashes ? '' : "---\n";

		// Start at the base of the array and move through it.
		if ($array !== []) {
			$string .= self::yamlizeArray($array, 0);
		}

		return $string;
	}

	/**
	 * Attempts to convert a key / value array item to YAML
	 *
	 * @access private
	 * @return string
	 *
	 * @param string $key    The name of the key
	 * @param mixed  $value  The value of the item
	 * @param int    $indent The indent of the current node
	 * @param array  $source
	 */
	private static function yamlize(string $key, $value, int $indent, array $source)
	{
		if (is_object($value)) {
			$value = (array) $value;
		}

		if (is_array($value)) {
			if ([] === $value) {
				return self::dumpNode($key, [], $indent, $source);
			}

			// It has children.  What to do?
			// Make it the right kind of item
			$string = self::dumpNode($key, self::EMPTY, $indent, $source);
			// Add the indent
			$indent += self::INDENT;

			// Yamlize the array
			$string .= self::yamlizeArray($value, $indent);
		} else {
			// It doesn't have children.  Yip.
			$string = self::dumpNode($key, $value, $indent, $source);
		}

		return $string;
	}

	/**
	 * Attempts to convert an array to YAML
	 *
	 * @param array $array  The array you want to convert
	 * @param int   $indent The indent of the current level
	 *
	 * @return string
	 */
	private static function yamlizeArray(array $array, int $indent): string
	{
		$string = '';
		foreach ($array as $key => $value) {
			$string .= self::yamlize($key, $value, $indent, $array);
		}

		return $string;
	}

	/**
	 * Returns YAML from a key and a value
	 *
	 * @param string $key    The name of the key
	 * @param mixed  $value  The value of the item
	 * @param int    $indent The indent of the current node
	 * @param array  $source
	 *
	 * @return string
	 */
	private static function dumpNode(string $key, $value, int $indent, array $source): string
	{
		// do some folding here, for blocks
		if (is_string($value) &&
			(
				(
					strpos($value, "\n") !== false ||
					strpos($value, ': ') !== false ||
					strpos($value, '- ') !== false ||
					strpos($value, '*') !== false ||
					strpos($value, '#') !== false ||
					strpos($value, '<') !== false ||
					strpos($value, '>') !== false ||
					strpos($value, '%') !== false ||
					strpos($value, '  ') !== false ||
					strpos($value, '[') !== false ||
					strpos($value, ']') !== false ||
					strpos($value, '{') !== false ||
					strpos($value, '}') !== false
				) ||
				strpos($value, '&') !== false ||
				strpos($value, "'") !== false ||
				strpos($value, '!') === 0 ||
				$value[strlen($value) - 1] === ':'
			)
		) {
			$value = self::doLiteralBlock($value, $indent);
		} else {
			$value = self::doFolding($value, $indent);
		}

		if ($value === []) {
			$value = '[ ]';
		} elseif ($value === '') {
			$value = '""';
		}

		if (self::isTranslationWord($value)) {
			$value = self::doLiteralBlock($value, $indent);
		}

		if (trim($value) !== $value) {
			$value = self::doLiteralBlock($value, $indent);
		}

		if (is_bool($value)) {
			$value = $value ? 'true' : 'false';
		} elseif ($value === null) {
			$value = 'null';
		} elseif ($value === "'" . self::EMPTY . "'") {
			$value = null;
		}

		$spaces = str_repeat(' ', $indent);

		if (!Arrays::isAssoc($source)) {
			// It's a sequence
			$string = $spaces . '- ' . $value . "\n";
		} else {
			// It's mapped
			if (strpos($key, ':') !== false || strpos($key, '#') !== false) {
				$key = '"' . $key . '"';
			}
			$string = rtrim($spaces . $key . ': ' . $value) . "\n";
		}

		return $string;
	}

	/**
	 * Creates a literal block for dumping
	 *
	 * @access private
	 * @return string
	 *
	 * @param $value
	 * @param $indent int The value of the indent
	 */
	private static function doLiteralBlock($value, $indent)
	{
		if ($value === "\n") {
			return '\n';
		}
		if (strpos($value, "\n") === false && strpos($value, "'") === false) {
			return sprintf("'%s'", $value);
		}
		if (strpos($value, "\n") === false && strpos($value, '"') === false) {
			return sprintf('"%s"', $value);
		}
		$exploded = explode("\n", $value);
		$newValue = '|';
		if (isset($exploded[0]) && ($exploded[0] === '|' || $exploded[0] === '|-' || $exploded[0] === '>')) {
			$newValue = $exploded[0];
			unset($exploded[0]);
		}
		$indent += self::INDENT;
		$spaces = str_repeat(' ', $indent);
		foreach ($exploded as $line) {
			$line = trim($line);
			if (
				(strpos($line, '"') === 0 && $line[strlen($line) - 1] === '"') ||
				(strpos($line, "'") === 0 && $line[strlen($line) - 1] === "'")
			) {
				$line = substr($line, 1, -1);
			}

			$newValue .= "\n" . $spaces . $line;
		}

		return $newValue;
	}

	/**
	 * Folds a string of text, if necessary
	 *
	 * @access private
	 * @return string
	 *
	 * @param string $value The string you wish to fold
	 */
	private static function doFolding($value, $indent)
	{
		$isString = is_string($value);

		// Don't do anything if wordwrap is set to 0
		if (self::$dumpWordWrap !== 0 && $isString && strlen($value) > self::$dumpWordWrap) {
			$indent += self::INDENT;
			$indent = str_repeat(' ', $indent);
			$wrapped = wordwrap($value, self::$dumpWordWrap, "\n$indent");
			$value = ">\n" . $indent . $wrapped;
		} else if (is_numeric($value) && $isString) {
			$value = '"' . $value . '"';
		}

		return $value;
	}

	private static function isTranslationWord($value): bool
	{
		$words = [
			'true', 'on', 'yes', 'y',
			'True', 'On', 'Yes', 'Y',
			'TRUE', 'ON', 'YES',

			'false', 'off', 'no', 'n',
			'False', 'Off', 'No', 'N',
			'FALSE', 'OFF', 'NO',

			'null', '~',
			'Null',
			'NULL',
		];

		return in_array($value, $words, true);
	}
}