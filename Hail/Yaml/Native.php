<?php
/**
 * Spyc -- A Simple PHP YAML Class
 *
 * @version   0.6.2
 * @author    Vlad Andersen <vlad.andersen@gmail.com>
 * @author    Chris Wanstrath <chris@ozmm.org>
 * @link      https://github.com/mustangostang/spyc/
 * @copyright Copyright 2005-2006 Chris Wanstrath, 2006-2011 Vlad Andersen
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 * @package   Spyc
 */

namespace Hail\Yaml;


class Native
{

	// SETTINGS

	const REMPTY = "\0\0\0\0\0";

	/**#@+
	 * @access private
	 * @var mixed
	 */
	private $_dumpIndent;
	private $_dumpWordWrap;
	private $_containsGroupAnchor = false;
	private $_containsGroupAlias = false;
	private $path;
	private $result;
	private $LiteralPlaceHolder = '___YAML_Literal_Block___';
	private $SavedGroups = [];
	private $indent;

	/**
	 * Path modifier that should be applied after adding current element.
	 *
	 * @var array
	 */
	private $delayedPath = [];

	/**#@+
	 * @access public
	 * @var mixed
	 */
	public $_nodeId;

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
	 * @access public
	 * @return string
	 *
	 * @param array $array    PHP array
	 * @param int   $indent   Pass in false to use the default, which is 2
	 * @param int   $wordwrap Pass in 0 for no wordwrap, false for default (40)
	 */
	public function dump($array, $indent = false, $wordwrap = false, $no_opening_dashes = false)
	{
		// Dumps to some very clean YAML.  We'll have to add some more features
		// and options soon.  And better support for folding.

		// New features and options.
		if ($indent === false or !is_numeric($indent)) {
			$this->_dumpIndent = 2;
		} else {
			$this->_dumpIndent = $indent;
		}

		if ($wordwrap === false or !is_numeric($wordwrap)) {
			$this->_dumpWordWrap = 40;
		} else {
			$this->_dumpWordWrap = $wordwrap;
		}

		// New YAML document
		$string = '';
		if (!$no_opening_dashes) {
			$string = "---\n";
		}

		// Start at the base of the array and move through it.
		if ($array) {
			$array = (array) $array;
			$previous_key = -1;
			foreach ($array as $key => $value) {
				if (!isset($first_key)) {
					$first_key = $key;
				}
				$string .= $this->_yamlize($key, $value, 0, $previous_key, $first_key, $array);
				$previous_key = $key;
			}
		}

		return $string;
	}

	/**
	 * Attempts to convert a key / value array item to YAML
	 *
	 * @access private
	 * @return string
	 *
	 * @param $key    The name of the key
	 * @param $value  The value of the item
	 * @param $indent The indent of the current node
	 */
	private function _yamlize($key, $value, $indent, $previous_key = -1, $first_key = 0, $source_array = null)
	{
		if (is_object($value)) {
			$value = (array) $value;
		}

		if (is_array($value)) {
			if (empty ($value)) {
				return $this->_dumpNode($key, [], $indent, $previous_key, $first_key, $source_array);
			}
			// It has children.  What to do?
			// Make it the right kind of item
			$string = $this->_dumpNode($key, self::REMPTY, $indent, $previous_key, $first_key, $source_array);
			// Add the indent
			$indent += $this->_dumpIndent;
			// Yamlize the array
			$string .= $this->_yamlizeArray($value, $indent);
		} else {
			// It doesn't have children.  Yip.
			$string = $this->_dumpNode($key, $value, $indent, $previous_key, $first_key, $source_array);
		}

		return $string;
	}

	/**
	 * Attempts to convert an array to YAML
	 *
	 * @access private
	 * @return string
	 *
	 * @param $array  The array you want to convert
	 * @param $indent The indent of the current level
	 */
	private function _yamlizeArray($array, $indent)
	{
		if (is_array($array)) {
			$string = '';
			$previous_key = -1;
			foreach ($array as $key => $value) {
				if (!isset($first_key)) {
					$first_key = $key;
				}
				$string .= $this->_yamlize($key, $value, $indent, $previous_key, $first_key, $array);
				$previous_key = $key;
			}

			return $string;
		} else {
			return false;
		}
	}

	/**
	 * Returns YAML from a key and a value
	 *
	 * @access private
	 * @return string
	 *
	 * @param $key    The name of the key
	 * @param $value  The value of the item
	 * @param $indent The indent of the current node
	 */
	private function _dumpNode($key, $value, $indent, $previous_key = -1, $first_key = 0, $source_array = null)
	{
		// do some folding here, for blocks
		if (is_string($value) && ((strpos($value, "\n") !== false || strpos($value, ": ") !== false || strpos($value, "- ") !== false ||
					strpos($value, "*") !== false || strpos($value, "#") !== false || strpos($value, "<") !== false || strpos($value, ">") !== false || strpos($value, '%') !== false || strpos($value, '  ') !== false ||
					strpos($value, "[") !== false || strpos($value, "]") !== false || strpos($value, "{") !== false || strpos($value, "}") !== false) || strpos($value, "&") !== false || strpos($value, "'") !== false || strpos($value, "!") === 0 ||
				substr($value, -1, 1) == ':')
		) {
			$value = $this->_doLiteralBlock($value, $indent);
		} else {
			$value = $this->_doFolding($value, $indent);
		}

		if ($value === []) {
			$value = '[ ]';
		}
		if ($value === "") {
			$value = '""';
		}
		if (self::isTranslationWord($value)) {
			$value = $this->_doLiteralBlock($value, $indent);
		}
		if (trim($value) != $value) {
			$value = $this->_doLiteralBlock($value, $indent);
		}

		if (is_bool($value)) {
			$value = $value ? "true" : "false";
		}

		if ($value === null) {
			$value = 'null';
		}
		if ($value === "'" . self::REMPTY . "'") {
			$value = null;
		}

		$spaces = str_repeat(' ', $indent);

		//if (is_int($key) && $key - 1 == $previous_key && $first_key===0) {
		if (is_array($source_array) && array_keys($source_array) === range(0, count($source_array) - 1)) {
			// It's a sequence
			$string = $spaces . '- ' . $value . "\n";
		} else {
			// if ($first_key===0)  throw new Exception('Keys are all screwy.  The first one was zero, now it\'s "'. $key .'"');
			// It's mapped
			if (strpos($key, ":") !== false || strpos($key, "#") !== false) {
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
	private function _doLiteralBlock($value, $indent)
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
		if (isset($exploded[0]) && ($exploded[0] == "|" || $exploded[0] == "|-" || $exploded[0] == ">")) {
			$newValue = $exploded[0];
			unset($exploded[0]);
		}
		$indent += $this->_dumpIndent;
		$spaces = str_repeat(' ', $indent);
		foreach ($exploded as $line) {
			$line = trim($line);
			if (strpos($line, '"') === 0 && strrpos($line, '"') == (strlen($line) - 1) || strpos($line, "'") === 0 && strrpos($line, "'") == (strlen($line) - 1)) {
				$line = substr($line, 1, -1);
			}
			$newValue .= "\n" . $spaces . ($line);
		}

		return $newValue;
	}

	/**
	 * Folds a string of text, if necessary
	 *
	 * @access private
	 * @return string
	 *
	 * @param $value The string you wish to fold
	 */
	private function _doFolding($value, $indent)
	{
		$isString = is_string($value);

		// Don't do anything if wordwrap is set to 0
		if ($this->_dumpWordWrap !== 0 && $isString && strlen($value) > $this->_dumpWordWrap) {
			$indent += $this->_dumpIndent;
			$indent = str_repeat(' ', $indent);
			$wrapped = wordwrap($value, $this->_dumpWordWrap, "\n$indent");
			$value = ">\n" . $indent . $wrapped;
		} else if (is_numeric($value) && $isString) {
			$value = '"' . $value . '"';
		}


		return $value;
	}

	private function isTrueWord($value)
	{
		$words = self::getTranslations(['true', 'on', 'yes', 'y']);

		return in_array($value, $words, true);
	}

	private function isFalseWord($value)
	{
		$words = self::getTranslations(['false', 'off', 'no', 'n']);

		return in_array($value, $words, true);
	}

	private function isNullWord($value)
	{
		$words = self::getTranslations(['null', '~']);

		return in_array($value, $words, true);
	}

	private function isTranslationWord($value)
	{
		return (
			self::isTrueWord($value) ||
			self::isFalseWord($value) ||
			self::isNullWord($value)
		);
	}

	/**
	 * Coerce a string into a native type
	 * Reference: http://yaml.org/type/bool.html
	 * TODO: Use only words from the YAML spec.
	 *
	 * @access private
	 *
	 * @param $value The value to coerce
	 */
	private function coerceValue(&$value)
	{
		if (self::isTrueWord($value)) {
			$value = true;
		} else if (self::isFalseWord($value)) {
			$value = false;
		} else if (self::isNullWord($value)) {
			$value = null;
		}
	}

	/**
	 * Given a set of words, perform the appropriate translations on them to
	 * match the YAML 1.1 specification for type coercing.
	 *
	 * @param $words The words to translate
	 *
	 * @access private
	 */
	private static function getTranslations(array $words)
	{
		$result = [];
		foreach ($words as $i) {
			$result = array_merge($result, [ucfirst($i), strtoupper($i), strtolower($i)]);
		}

		return $result;
	}

// LOADING FUNCTIONS

	public function load($input)
	{
		$source = $this->loadFromSource($input);

		return $this->loadWithSource($source);
	}

	public function loadString($input)
	{
		$source = $this->loadFromString($input);

		return $this->loadWithSource($source);
	}

	private function loadWithSource($source)
	{
		if (empty ($source)) {
			return [];
		}

		$this->path = [];
		$this->result = [];

		$cnt = count($source);
		for ($i = 0; $i < $cnt; $i++) {
			$line = $source[$i];

			$this->indent = strlen($line) - strlen(ltrim($line));
			$tempPath = $this->getParentPathByIndent($this->indent);
			$line = self::stripIndent($line, $this->indent);
			if (self::isComment($line)) {
				continue;
			}
			if (self::isEmpty($line)) {
				continue;
			}
			$this->path = $tempPath;

			$literalBlockStyle = self::startsLiteralBlock($line);
			if ($literalBlockStyle) {
				$line = rtrim($line, $literalBlockStyle . " \n");
				$literalBlock = '';
				$line .= ' ' . $this->LiteralPlaceHolder;
				$literal_block_indent = strlen($source[$i + 1]) - strlen(ltrim($source[$i + 1]));
				while (++$i < $cnt && $this->literalBlockContinues($source[$i], $this->indent)) {
					$literalBlock = $this->addLiteralLine($literalBlock, $source[$i], $literalBlockStyle, $literal_block_indent);
				}
				$i--;
			}

			// Strip out comments
			if (strpos($line, '#')) {
				$line = preg_replace('/\s*#([^"\']+)$/', '', $line);
			}

			while (++$i < $cnt && self::greedilyNeedNextLine($line)) {
				$line = rtrim($line, " \n\t\r") . ' ' . ltrim($source[$i], " \t");
			}
			$i--;

			$lineArray = $this->_parseLine($line);

			if ($literalBlockStyle) {
				$lineArray = $this->revertLiteralPlaceHolder($lineArray, $literalBlock);
			}

			$this->addArray($lineArray, $this->indent);

			foreach ($this->delayedPath as $indent => $delayedPath) {
				$this->path[$indent] = $delayedPath;
			}

			$this->delayedPath = [];

		}

		return $this->result;
	}

	private function loadFromSource($input)
	{
		if ($input !== '' && file_exists($input)) {
			$input = file_get_contents($input);
		}

		return $this->loadFromString($input);
	}

	private function loadFromString($input)
	{
		$lines = explode("\n", $input);
		foreach ($lines as &$v) {
			$v = rtrim($v, "\r");
		}

		return $lines;
	}

	/**
	 * Parses YAML code and returns an array for a node
	 *
	 * @access private
	 * @return array
	 *
	 * @param string $line A line from the YAML file
	 */
	private function _parseLine($line)
	{
		if (!$line) {
			return [];
		}
		$line = trim($line);
		if (!$line) {
			return [];
		}

		$array = [];

		$group = $this->nodeContainsGroup($line);
		if ($group) {
			$this->addGroup($line, $group);
			$line = $this->stripGroup($line, $group);
		}

		if ($this->startsMappedSequence($line)) {
			return $this->returnMappedSequence($line);
		}

		if ($this->startsMappedValue($line)) {
			return $this->returnMappedValue($line);
		}

		if ($this->isArrayElement($line)) {
			return $this->returnArrayElement($line);
		}

		if ($this->isPlainArray($line)) {
			return $this->returnPlainArray($line);
		}


		return $this->returnKeyValuePair($line);

	}

	/**
	 * Finds the type of the passed value, returns the value as the new type.
	 *
	 * @access private
	 *
	 * @param string $value
	 *
	 * @return mixed
	 */
	private function _toType($value)
	{
		if ($value === '') {
			return "";
		}
		$first_character = $value[0];
		$last_character = substr($value, -1, 1);

		$is_quoted = false;
		do {
			if (!$value) {
				break;
			}
			if ($first_character != '"' && $first_character != "'") {
				break;
			}
			if ($last_character != '"' && $last_character != "'") {
				break;
			}
			$is_quoted = true;
		} while (0);

		if ($is_quoted) {
			$value = str_replace('\n', "\n", $value);
			if ($first_character == "'") {
				return strtr(substr($value, 1, -1), ['\'\'' => '\'', '\\\'' => '\'']);
			}

			return strtr(substr($value, 1, -1), ['\\"' => '"', '\\\'' => '\'']);
		}

		if (strpos($value, ' #') !== false && !$is_quoted) {
			$value = preg_replace('/\s+#(.+)$/', '', $value);
		}

		if ($first_character == '[' && $last_character == ']') {
			// Take out strings sequences and mappings
			$innerValue = trim(substr($value, 1, -1));
			if ($innerValue === '') {
				return [];
			}
			$explode = $this->_inlineEscape($innerValue);
			// Propagate value array
			$value = [];
			foreach ($explode as $v) {
				$value[] = $this->_toType($v);
			}

			return $value;
		}

		if (strpos($value, ': ') !== false && $first_character != '{') {
			$array = explode(': ', $value);
			$key = trim($array[0]);
			array_shift($array);
			$value = trim(implode(': ', $array));
			$value = $this->_toType($value);

			return [$key => $value];
		}

		if ($first_character == '{' && $last_character == '}') {
			$innerValue = trim(substr($value, 1, -1));
			if ($innerValue === '') {
				return [];
			}
			// Inline Mapping
			// Take out strings sequences and mappings
			$explode = $this->_inlineEscape($innerValue);
			// Propagate value array
			$array = [];
			foreach ($explode as $v) {
				$SubArr = $this->_toType($v);
				if (empty($SubArr)) {
					continue;
				}
				if (is_array($SubArr)) {
					$array[key($SubArr)] = $SubArr[key($SubArr)];
					continue;
				}
				$array[] = $SubArr;
			}

			return $array;
		}

		if ($value == 'null' || $value == 'NULL' || $value == 'Null' || $value == '' || $value == '~') {
			return null;
		}

		if (is_numeric($value) && preg_match('/^(-|)[1-9]+[0-9]*$/', $value)) {
			$intvalue = (int) $value;
			if ($intvalue != PHP_INT_MAX && $intvalue != ~PHP_INT_MAX) {
				$value = $intvalue;
			}

			return $value;
		}

		if (is_string($value) && preg_match('/^0[xX][0-9a-fA-F]+$/', $value)) {
			// Hexadecimal value.
			return hexdec($value);
		}

		$this->coerceValue($value);

		if (is_numeric($value)) {
			if ($value === '0') {
				return 0;
			}
			if (rtrim($value, 0) === $value) {
				$value = (float) $value;
			}

			return $value;
		}

		return $value;
	}

	/**
	 * Used in inlines to check for more inlines or quoted strings
	 *
	 * @access private
	 * @return array
	 */
	private function _inlineEscape($inline)
	{
		// There's gotta be a cleaner way to do this...
		// While pure sequences seem to be nesting just fine,
		// pure mappings and mappings with sequences inside can't go very
		// deep.  This needs to be fixed.

		$seqs = [];
		$maps = [];
		$saved_strings = [];
		$saved_empties = [];

		// Check for empty strings
		$regex = '/("")|(\'\')/';
		if (preg_match_all($regex, $inline, $strings)) {
			$saved_empties = $strings[0];
			$inline = preg_replace($regex, 'YAMLEmpty', $inline);
		}
		unset($regex);

		// Check for strings
		$regex = '/(?:(")|(?:\'))((?(1)[^"]+|[^\']+))(?(1)"|\')/';
		if (preg_match_all($regex, $inline, $strings)) {
			$saved_strings = $strings[0];
			$inline = preg_replace($regex, 'YAMLString', $inline);
		}
		unset($regex);

		// echo $inline;

		$i = 0;
		do {

			// Check for sequences
			while (preg_match('/\[([^{}\[\]]+)\]/U', $inline, $matchseqs)) {
				$seqs[] = $matchseqs[0];
				$inline = preg_replace('/\[([^{}\[\]]+)\]/U', ('YAMLSeq' . (count($seqs) - 1) . 's'), $inline, 1);
			}

			// Check for mappings
			while (preg_match('/{([^\[\]{}]+)}/U', $inline, $matchmaps)) {
				$maps[] = $matchmaps[0];
				$inline = preg_replace('/{([^\[\]{}]+)}/U', ('YAMLMap' . (count($maps) - 1) . 's'), $inline, 1);
			}

			if ($i++ >= 10) {
				break;
			}

		} while (strpos($inline, '[') !== false || strpos($inline, '{') !== false);

		$explode = explode(',', $inline);
		$explode = array_map('trim', $explode);
		$stringi = 0;
		$i = 0;

		while (1) {

			// Re-add the sequences
			if (!empty($seqs)) {
				foreach ($explode as $key => $value) {
					if (strpos($value, 'YAMLSeq') !== false) {
						foreach ($seqs as $seqk => $seq) {
							$explode[$key] = str_replace(('YAMLSeq' . $seqk . 's'), $seq, $value);
							$value = $explode[$key];
						}
					}
				}
			}

			// Re-add the mappings
			if (!empty($maps)) {
				foreach ($explode as $key => $value) {
					if (strpos($value, 'YAMLMap') !== false) {
						foreach ($maps as $mapk => $map) {
							$explode[$key] = str_replace(('YAMLMap' . $mapk . 's'), $map, $value);
							$value = $explode[$key];
						}
					}
				}
			}


			// Re-add the strings
			if (!empty($saved_strings)) {
				foreach ($explode as $key => $value) {
					while (strpos($value, 'YAMLString') !== false) {
						$explode[$key] = preg_replace('/YAMLString/', $saved_strings[$stringi], $value, 1);
						unset($saved_strings[$stringi]);
						++$stringi;
						$value = $explode[$key];
					}
				}
			}


			// Re-add the empties
			if (!empty($saved_empties)) {
				foreach ($explode as $key => $value) {
					while (strpos($value, 'YAMLEmpty') !== false) {
						$explode[$key] = preg_replace('/YAMLEmpty/', '', $value, 1);
						$value = $explode[$key];
					}
				}
			}

			$finished = true;
			foreach ($explode as $key => $value) {
				if (strpos($value, 'YAMLSeq') !== false) {
					$finished = false;
					break;
				}
				if (strpos($value, 'YAMLMap') !== false) {
					$finished = false;
					break;
				}
				if (strpos($value, 'YAMLString') !== false) {
					$finished = false;
					break;
				}
				if (strpos($value, 'YAMLEmpty') !== false) {
					$finished = false;
					break;
				}
			}
			if ($finished) {
				break;
			}

			$i++;
			if ($i > 10) {
				break;
			} // Prevent infinite loops.
		}


		return $explode;
	}

	private function literalBlockContinues($line, $lineIndent)
	{
		if (!trim($line)) {
			return true;
		}
		if (strlen($line) - strlen(ltrim($line)) > $lineIndent) {
			return true;
		}

		return false;
	}

	private function referenceContentsByAlias($alias)
	{
		do {
			if (!isset($this->SavedGroups[$alias])) {
				echo "Bad group name: $alias.";
				break;
			}
			$groupPath = $this->SavedGroups[$alias];
			$value = $this->result;
			foreach ($groupPath as $k) {
				$value = $value[$k];
			}
		} while (false);

		return $value;
	}

	private function addArrayInline($array, $indent)
	{
		$CommonGroupPath = $this->path;
		if (empty ($array)) {
			return false;
		}

		foreach ($array as $k => $_) {
			$this->addArray([$k => $_], $indent);
			$this->path = $CommonGroupPath;
		}

		return true;
	}

	private function addArray($incoming_data, $incoming_indent)
	{

		// print_r ($incoming_data);

		if (count($incoming_data) > 1) {
			return $this->addArrayInline($incoming_data, $incoming_indent);
		}

		$key = key($incoming_data);
		$value = isset($incoming_data[$key]) ? $incoming_data[$key] : null;
		if ($key === '__!YAMLZero') {
			$key = '0';
		}

		if ($incoming_indent == 0 && !$this->_containsGroupAlias && !$this->_containsGroupAnchor) { // Shortcut for root-level values.
			if ($key || $key === '' || $key === '0') {
				$this->result[$key] = $value;
			} else {
				$this->result[] = $value;
				end($this->result);
				$key = key($this->result);
			}
			$this->path[$incoming_indent] = $key;

			return;
		}


		$history = [];
		// Unfolding inner array tree.
		$history[] = $_arr = $this->result;
		foreach ($this->path as $k) {
			$history[] = $_arr = $_arr[$k];
		}

		if ($this->_containsGroupAlias) {
			$value = $this->referenceContentsByAlias($this->_containsGroupAlias);
			$this->_containsGroupAlias = false;
		}


		// Adding string or numeric key to the innermost level or $this->arr.
		if (is_string($key) && $key == '<<') {
			if (!is_array($_arr)) {
				$_arr = [];
			}

			$_arr = array_merge($_arr, $value);
		} else if ($key || $key === '' || $key === '0') {
			if (!is_array($_arr)) {
				$_arr = [$key => $value];
			} else {
				$_arr[$key] = $value;
			}
		} else {
			if (!is_array($_arr)) {
				$_arr = [$value];
				$key = 0;
			} else {
				$_arr[] = $value;
				end($_arr);
				$key = key($_arr);
			}
		}

		$reverse_path = array_reverse($this->path);
		$reverse_history = array_reverse($history);
		$reverse_history[0] = $_arr;
		$cnt = count($reverse_history) - 1;
		for ($i = 0; $i < $cnt; $i++) {
			$reverse_history[$i + 1][$reverse_path[$i]] = $reverse_history[$i];
		}
		$this->result = $reverse_history[$cnt];

		$this->path[$incoming_indent] = $key;

		if ($this->_containsGroupAnchor) {
			$this->SavedGroups[$this->_containsGroupAnchor] = $this->path;
			if (is_array($value)) {
				$k = key($value);
				if (!is_int($k)) {
					$this->SavedGroups[$this->_containsGroupAnchor][$incoming_indent + 2] = $k;
				}
			}
			$this->_containsGroupAnchor = false;
		}

	}

	private static function startsLiteralBlock($line)
	{
		$lastChar = substr(trim($line), -1);
		if ($lastChar != '>' && $lastChar != '|') {
			return false;
		}
		if ($lastChar == '|') {
			return $lastChar;
		}
		// HTML tags should not be counted as literal blocks.
		if (preg_match('#<.*?>$#', $line)) {
			return false;
		}

		return $lastChar;
	}

	private static function greedilyNeedNextLine($line)
	{
		$line = trim($line);
		if (!strlen($line)) {
			return false;
		}
		if (substr($line, -1, 1) == ']') {
			return false;
		}
		if ($line[0] == '[') {
			return true;
		}
		if (preg_match('#^[^:]+?:\s*\[#', $line)) {
			return true;
		}

		return false;
	}

	private function addLiteralLine($literalBlock, $line, $literalBlockStyle, $indent = -1)
	{
		$line = self::stripIndent($line, $indent);
		if ($literalBlockStyle !== '|') {
			$line = self::stripIndent($line);
		}
		$line = rtrim($line, "\r\n\t ") . "\n";
		if ($literalBlockStyle == '|') {
			return $literalBlock . $line;
		}
		if (strlen($line) == 0) {
			return rtrim($literalBlock, ' ') . "\n";
		}
		if ($line == "\n" && $literalBlockStyle == '>') {
			return rtrim($literalBlock, " \t") . "\n";
		}
		if ($line != "\n") {
			$line = trim($line, "\r\n ") . " ";
		}

		return $literalBlock . $line;
	}

	function revertLiteralPlaceHolder($lineArray, $literalBlock)
	{
		foreach ($lineArray as $k => $_) {
			if (is_array($_)) {
				$lineArray[$k] = $this->revertLiteralPlaceHolder($_, $literalBlock);
			} else if (substr($_, -1 * strlen($this->LiteralPlaceHolder)) == $this->LiteralPlaceHolder) {
				$lineArray[$k] = rtrim($literalBlock, " \r\n");
			}
		}

		return $lineArray;
	}

	private static function stripIndent($line, $indent = -1)
	{
		if ($indent == -1) {
			$indent = strlen($line) - strlen(ltrim($line));
		}

		return substr($line, $indent);
	}

	private function getParentPathByIndent($indent)
	{
		if ($indent == 0) {
			return [];
		}
		$linePath = $this->path;
		do {
			end($linePath);
			$lastIndentInParentPath = key($linePath);
			if ($indent <= $lastIndentInParentPath) {
				array_pop($linePath);
			}
		} while ($indent <= $lastIndentInParentPath);

		return $linePath;
	}

	private static function isComment($line)
	{
		if (!$line) {
			return false;
		}

		if ($line[0] === '#') {
			return true;
		}

		if (trim($line, " \r\n\t") == '---') {
			return true;
		}

		return false;
	}

	private static function isEmpty($line)
	{
		return (trim($line) === '');
	}


	private function isArrayElement($line)
	{
		if (!$line || !is_scalar($line)) {
			return false;
		}

		if (
			strpos($line, '- ') !== 0 ||
			strpos($line, '---') === 0
		) {
			return false;
		}

		return true;
	}

	private function isHashElement($line)
	{
		return strpos($line, ':');
	}

	private static function unquote($value)
	{
		if (!$value) {
			return $value;
		}

		if (!is_string($value)) {
			return $value;
		}

		if ($value[0] === '\'') {
			return trim($value, '\'');
		}

		if ($value[0] === '"') {
			return trim($value, '"');
		}

		return $value;
	}

	private function startsMappedSequence($line)
	{
		return (strpos($line, '- ') === 0 && $line[strlen($line) - 1] === ':');
	}

	private function returnMappedSequence($line)
	{
		$array = [];
		$key = self::unquote(trim(substr($line, 1, -1)));
		$array[$key] = [];
		$this->delayedPath = [strpos($line, $key) + $this->indent => $key];

		return [$array];
	}

	private function checkKeysInValue($value)
	{
		if (
			strpos($value, ': ') !== false &&
			strpos('[{"\'', $value[0]) === false
		) {
			throw new \RuntimeException('Too many keys: ' . $value);
		}
	}

	private function returnMappedValue($line)
	{
		$this->checkKeysInValue($line);
		$array = [];
		$key = self::unquote(trim(substr($line, 0, -1)));
		$array[$key] = '';

		return $array;
	}

	private function startsMappedValue($line)
	{
		return ($line[strlen($line) - 1] === ':');
	}

	private function isPlainArray($line)
	{
		return ($line[0] === '[' && $line[strlen($line) - 1] === ']');
	}

	private function returnPlainArray($line)
	{
		return $this->_toType($line);
	}

	private function returnKeyValuePair($line)
	{
		$array = [];
		$key = '';
		if (strpos($line, ': ')) {
			// It's a key/value pair most likely
			// If the key is in double quotes pull it out
			if (($line[0] === '"' || $line[0] === "'") && preg_match('/^(["\'](.*)["\'](\s)*:)/', $line, $matches)) {
				$value = trim(str_replace($matches[1], '', $line));
				$key = $matches[2];
			} else {
				// Do some guesswork as to the key and the value
				$explode = explode(': ', $line);
				$key = trim(array_shift($explode));
				$value = trim(implode(': ', $explode));
				$this->checkKeysInValue($value);
			}
			// Set the type of the value.  Int, string, etc
			$value = $this->_toType($value);
			if ($key === '0') {
				$key = '__!YAMLZero';
			}
			$array[$key] = $value;
		} else {
			$array = [$line];
		}

		return $array;

	}


	private function returnArrayElement($line)
	{
		if (strlen($line) <= 1) {
			return [[]];
		} // Weird %)
		$array = [];
		$value = trim(substr($line, 1));
		$value = $this->_toType($value);
		if ($this->isArrayElement($value)) {
			$value = $this->returnArrayElement($value);
		}
		$array[] = $value;

		return $array;
	}


	private function nodeContainsGroup($line)
	{
		$symbolsForReference = 'A-z0-9_\-';
		if (strpos($line, '&') === false && strpos($line, '*') === false) {
			return false;
		} // Please die fast ;-)
		if ($line[0] === '&' && preg_match('/^(&[' . $symbolsForReference . ']+)/', $line, $matches)) {
			return $matches[1];
		}
		if ($line[0] === '*' && preg_match('/^(\*[' . $symbolsForReference . ']+)/', $line, $matches)) {
			return $matches[1];
		}
		if (preg_match('/(&[' . $symbolsForReference . ']+)$/', $line, $matches)) {
			return $matches[1];
		}
		if (preg_match('/(\*[' . $symbolsForReference . ']+$)/', $line, $matches)) {
			return $matches[1];
		}
		if (preg_match('#^\s*<<\s*:\s*(\*[^\s]+).*$#', $line, $matches)) {
			return $matches[1];
		}

		return false;

	}

	private function addGroup($line, $group)
	{
		if ($group[0] === '&') {
			$this->_containsGroupAnchor = substr($group, 1);
		}
		if ($group[0] === '*') {
			$this->_containsGroupAlias = substr($group, 1);
		}
		//print_r ($this->path);
	}

	private function stripGroup($line, $group)
	{
		$line = trim(str_replace($group, '', $line));

		return $line;
	}
}