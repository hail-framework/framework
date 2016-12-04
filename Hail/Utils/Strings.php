<?php
namespace Hail\Utils;

use Hail\Exception\RegexpException;


/**
 * String tools library.
 *
 * @package Hail\Utils
 * @author  Hao Feng <flyinghail@msn.com>
 */
class Strings
{
	use Singleton;

	const TRIM_CHARACTERS = " \t\n\r\0\x0B\xC2\xA0";


	/**
	 * Checks if the string is valid for UTF-8 encoding.
	 *
	 * @param  string $s byte stream to check
	 *
	 * @return bool
	 */
	public function checkEncoding(string $s): bool
	{
		return $s === $this->fixEncoding($s);
	}


	/**
	 * Removes invalid code unit sequences from UTF-8 string.
	 *
	 * @param  string $s byte stream to fix
	 *
	 * @return string
	 */
	public function fixEncoding(string $s): string
	{
		// removes xD800-xDFFF, x110000 and higher
		return htmlspecialchars_decode(htmlspecialchars($s, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES);
	}


	/**
	 * Returns a specific character in UTF-8.
	 *
	 * @param  int $code code point (0x0 to 0xD7FF or 0xE000 to 0x10FFFF)
	 *
	 * @return string
	 * @throws \InvalidArgumentException if code point is not in valid range
	 */
	public function chr(int $code): string
	{
		if ($code < 0 || ($code >= 0xD800 && $code <= 0xDFFF) || $code > 0x10FFFF) {
			throw new \InvalidArgumentException('Code point must be in range 0x0 to 0xD7FF or 0xE000 to 0x10FFFF.');
		}

		if (function_exists('iconv')) {
			return iconv('UTF-32BE', 'UTF-8//IGNORE', pack('N', $code));
		}

		if (0x80 > $code %= 0x200000) {
			return chr($code);
		}
		if (0x800 > $code) {
			return chr(0xC0 | $code >> 6) . chr(0x80 | $code & 0x3F);
		}
		if (0x10000 > $code) {
			return chr(0xE0 | $code >> 12) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F);
		}

		return chr(0xF0 | $code >> 18) . chr(0x80 | $code >> 12 & 0x3F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F);
	}


	/**
	 * Starts the $haystack string with the prefix $needle?
	 *
	 * @param  string
	 * @param  string
	 *
	 * @return bool
	 */
	public function startsWith(string $haystack, string $needle): bool
	{
		return strpos($haystack, $needle) === 0;
	}


	/**
	 * Ends the $haystack string with the suffix $needle?
	 *
	 * @param  string
	 * @param  string
	 *
	 * @return bool
	 */
	public function endsWith(string $haystack, string $needle): bool
	{
		return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
	}


	/**
	 * Determine if a given string contains a given substring.
	 *
	 * @param  string       $haystack
	 * @param  string|array $needles
	 *
	 * @return bool
	 */
	public function contains(string $haystack, $needles): bool
	{
		foreach ((array) $needles as $needle) {
			if ($needle !== '' && mb_strpos($haystack, $needle) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes special controls characters and normalizes line endings and spaces.
	 *
	 * @param  string $s UTF-8 encoding
	 *
	 * @return string
	 */
	public function normalize(string $s): string
	{
		$s = $this->normalizeNewLines($s);

		// remove control characters; leave \t + \n
		$s = preg_replace('#[\x00-\x08\x0B-\x1F\x7F-\x9F]+#u', '', $s);

		// right trim
		$s = preg_replace('#[\t ]+$#m', '', $s);

		// leading and trailing blank lines
		$s = trim($s, "\n");

		return $s;
	}


	/**
	 * Standardize line endings to unix-like.
	 *
	 * @param  string $s UTF-8 encoding or 8-bit
	 *
	 * @return string
	 */
	public function normalizeNewLines(string $s): string
	{
		return str_replace(["\r\n", "\r"], "\n", $s);
	}


	/**
	 * Converts to ASCII.
	 *
	 * @param  string $s UTF-8 encoding
	 *
	 * @return string  ASCII
	 */
	public function toAscii(string $s): string
	{
		static $transliterator = null;
		if ($transliterator === null && class_exists('Transliterator', false)) {
			$transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
		}

		$s = preg_replace('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{2FF}\x{370}-\x{10FFFF}]#u', '', $s);
		$s = strtr($s, '`\'"^~?', "\x01\x02\x03\x04\x05\x06");
		$s = str_replace(
			["\xE2\x80\x9E", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9A", "\xE2\x80\x98", "\xE2\x80\x99", "\xC2\xB0"],
			["\x03", "\x03", "\x03", "\x02", "\x02", "\x02", "\x04"], $s
		);
		if ($transliterator !== null) {
			$s = $transliterator->transliterate($s);
		}
		if (ICONV_IMPL === 'glibc') {
			$s = str_replace(
				["\xC2\xBB", "\xC2\xAB", "\xE2\x80\xA6", "\xE2\x84\xA2", "\xC2\xA9", "\xC2\xAE"],
				['>>', '<<', '...', 'TM', '(c)', '(R)'], $s
			);
			$s = iconv('UTF-8', 'WINDOWS-1250//TRANSLIT//IGNORE', $s);
			$s = strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e"
				. "\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3"
				. "\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8"
				. "\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe"
				. "\x96\xa0\x8b\x97\x9b\xa6\xad\xb7",
				'ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt- <->|-.');
			$s = preg_replace('#[^\x00-\x7F]++#', '', $s);
		} else {
			$s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
		}
		$s = str_replace(['`', "'", '"', '^', '~', '?'], '', $s);

		return strtr($s, "\x01\x02\x03\x04\x05\x06", '`\'"^~?');
	}


	/**
	 * Converts to web safe characters [a-z0-9-] text.
	 *
	 * @param  string $s        UTF-8 encoding
	 * @param  string $charList allowed characters
	 * @param  bool   $lower
	 *
	 * @return string
	 */
	public function webalize(string $s, string $charList = null, bool $lower = true): string
	{
		$s = $this->toAscii($s);
		if ($lower) {
			$s = strtolower($s);
		}
		$s = preg_replace('#[^a-z0-9' . ($charList !== null ? preg_quote($charList, '#') : '') . ']+#i', '-', $s);
		$s = trim($s, '-');

		return $s;
	}


	/**
	 * Truncates string to maximal length.
	 *
	 * @param  string $s      UTF-8 encoding
	 * @param  int    $maxLen
	 * @param  string $append UTF-8 encoding
	 *
	 * @return string
	 */
	public function truncate(string $s, int $maxLen, string $append = "\xE2\x80\xA6"): string
	{
		if (mb_strlen($s) > $maxLen) {
			$maxLen -= mb_strlen($append);
			if ($maxLen < 1) {
				return $append;

			} elseif ($matches = $this->match($s, '#^.{1,' . $maxLen . '}(?=[\s\x00-/:-@\[-`{-~])#us')) {
				return $matches[0] . $append;

			} else {
				return mb_substr($s, 0, $maxLen) . $append;
			}
		}

		return $s;
	}


	/**
	 * Indents the content from the left.
	 *
	 * @param  string $s UTF-8 encoding or 8-bit
	 * @param  int    $level
	 * @param  string $chars
	 *
	 * @return string
	 */
	public function indent(string $s, int $level = 1, string $chars = "\t"): string
	{
		if ($level > 0) {
			$s = $this->replace($s, '#(?:^|[\r\n]+)(?=[^\r\n])#', '$0' . str_repeat($chars, $level));
		}

		return $s;
	}


	/**
	 * Convert first character to lower case.
	 *
	 * @param  string $s UTF-8 encoding
	 *
	 * @return string
	 */
	public static function firstLower(string $s): string
	{
		return mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1);
	}


	/**
	 * Convert first character to upper case.
	 *
	 * @param  string $s UTF-8 encoding
	 *
	 * @return string
	 */
	public function firstUpper(string $s): string
	{
		return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
	}

	/**
	 * Case-insensitive compares UTF-8 strings.
	 *
	 * @param  string
	 * @param  string
	 * @param  int
	 *
	 * @return bool
	 */
	public function compare(string $left, string $right, int $len = null)
	{
		if ($len < 0) {
			$left = mb_substr($left, $len, -$len);
			$right = mb_substr($right, $len, -$len);
		} elseif ($len !== null) {
			$left = mb_substr($left, 0, $len);
			$right = mb_substr($right, 0, $len);
		}

		return mb_strtolower($left) === mb_strtolower($right);
	}


	/**
	 * Finds the length of common prefix of strings.
	 *
	 * @param array ...$strings
	 *
	 * @return string
	 */
	public function findPrefix(...$strings): string
	{
		if (is_array($strings[0])) {
			$strings = $strings[0];
		}
		$first = array_shift($strings);
		for ($i = 0, $n = strlen($first); $i < $n; $i++) {
			foreach ($strings as $s) {
				if (!isset($s[$i]) || $first[$i] !== $s[$i]) {
					while ($i && $first[$i - 1] >= "\x80" && $first[$i] >= "\x80" && $first[$i] < "\xC0") {
						$i--;
					}

					return substr($first, 0, $i);
				}
			}
		}

		return $first;
	}

	/**
	 * Strips whitespace.
	 *
	 * @param  string $s UTF-8 encoding
	 * @param  string $charList
	 *
	 * @return string
	 * @throws RegexpException
	 */
	public function trim(string $s, string $charList = self::TRIM_CHARACTERS): string
	{
		$charList = preg_quote($charList, '#');

		return $this->replace($s, '#^[' . $charList . ']+|[' . $charList . ']+\z#u', '');
	}


	/**
	 * Pad a string to a certain length with another string.
	 *
	 * @param  string $s UTF-8 encoding
	 * @param  int    $length
	 * @param  string $pad
	 *
	 * @return string
	 */
	public function padLeft(string $s, int $length, string $pad = ' '): string
	{
		$length = max(0, $length - mb_strlen($s));
		$padLen = mb_strlen($pad);

		return str_repeat($pad, (int) ($length / $padLen)) . mb_substr($pad, 0, $length % $padLen) . $s;
	}


	/**
	 * Pad a string to a certain length with another string.
	 *
	 * @param string $s UTF-8 encoding
	 * @param int    $length
	 * @param string $pad
	 *
	 * @return string
	 */
	public function padRight(string $s, int $length, string $pad = ' '): string
	{
		$length = max(0, $length - mb_strlen($s));
		$padLen = mb_strlen($pad);

		return $s . str_repeat($pad, (int) ($length / $padLen)) . mb_substr($pad, 0, $length % $padLen);
	}


	/**
	 * Reverse string.
	 *
	 * @param  string $s UTF-8 encoding
	 *
	 * @return string
	 */
	public function reverse(string $s): string
	{
		if (function_exists('iconv')) {
			return iconv('UTF-32LE', 'UTF-8', strrev(iconv('UTF-8', 'UTF-32BE', $s)));
		}

		preg_match_all('/./us', $s, $ar);

		return implode('', array_reverse($ar[0]));
	}


	/**
	 * Returns part of $haystack before $nth occurence of $needle.
	 *
	 * @param  string $haystack
	 * @param  string $needle
	 * @param  int    $nth negative value means searching from the end
	 *
	 * @return string|FALSE  returns FALSE if the needle was not found
	 */
	public function before(string $haystack, string $needle, int $nth = 1)
	{
		$pos = self::pos($haystack, $needle, $nth);

		return $pos === false
			? false
			: substr($haystack, 0, $pos);
	}


	/**
	 * Returns part of $haystack after $nth occurence of $needle.
	 *
	 * @param  string $haystack
	 * @param  string $needle
	 * @param  int    $nth negative value means searching from the end
	 *
	 * @return string|FALSE  returns FALSE if the needle was not found
	 */
	public function after(string $haystack, string $needle, int $nth = 1)
	{
		$pos = self::pos($haystack, $needle, $nth);

		return $pos === false
			? false
			: (string) substr($haystack, $pos + strlen($needle));
	}


	/**
	 * Returns position of $nth occurence of $needle in $haystack.
	 *
	 * @param  string $haystack
	 * @param  string $needle
	 * @param  int    $nth negative value means searching from the end
	 *
	 * @return int|FALSE  offset in characters or FALSE if the needle was not found
	 */
	public function indexOf(string $haystack, string $needle, int $nth = 1)
	{
		$pos = self::pos($haystack, $needle, $nth);

		return $pos === false
			? false
			: mb_strlen(substr($haystack, 0, $pos));
	}

	/**
	 * Returns position of $nth occurence of $needle in $haystack.
	 *
	 * @param string $haystack
	 * @param string $needle
	 * @param int    $nth
	 *
	 * @return bool|int  offset in bytes or FALSE if the needle was not found
	 */
	private static function pos(string $haystack, string $needle, $nth = 1)
	{
		if (!$nth) {
			return false;
		} elseif ($nth > 0) {
			if ($needle === '') {
				return 0;
			}
			$pos = 0;
			while (false !== ($pos = strpos($haystack, $needle, $pos)) && --$nth) {
				$pos++;
			}
		} else {
			$len = strlen($haystack);
			if ($needle === '') {
				return $len;
			}
			$pos = $len - 1;
			while (false !== ($pos = strrpos($haystack, $needle, $pos - $len)) && ++$nth) {
				$pos--;
			}
		}

		return $pos;
	}


	/**
	 * Splits string by a regular expression.
	 *
	 * @param  string $subject
	 * @param  string $pattern
	 * @param  int    $flags
	 *
	 * @return array
	 * @throws RegexpException
	 */
	public function split(string $subject, string $pattern, int $flags = 0): array
	{
		return self::pcre('preg_split', [$pattern, $subject, -1, $flags | PREG_SPLIT_DELIM_CAPTURE]);
	}


	/**
	 * Performs a regular expression match.
	 *
	 * @param  string $subject
	 * @param  string $pattern
	 * @param  int    $flags  can be PREG_OFFSET_CAPTURE (returned in bytes)
	 * @param  int    $offset offset in bytes
	 *
	 * @return mixed
	 * @throws RegexpException
	 */
	public function match(string $subject, string $pattern, int $flags = 0, int $offset = 0)
	{
		if ($offset > strlen($subject)) {
			return null;
		}

		return self::pcre('preg_match', [$pattern, $subject, & $m, $flags, $offset])
			? $m
			: null;
	}


	/**
	 * Performs a global regular expression match.
	 *
	 * @param  string $subject
	 * @param  string $pattern
	 * @param  int    $flags  can be PREG_OFFSET_CAPTURE (returned in bytes); PREG_SET_ORDER is default
	 * @param  int    $offset offset in bytes
	 *
	 * @return array
	 * @throws RegexpException
	 */
	public function matchAll(string $subject, string $pattern, int $flags = 0, int $offset = 0): array
	{
		if ($offset > strlen($subject)) {
			return [];
		}
		self::pcre('preg_match_all', [
			$pattern, $subject, &$m,
			($flags & PREG_PATTERN_ORDER) ? $flags : ($flags | PREG_SET_ORDER),
			$offset,
		]);

		return $m;
	}


	/**
	 * Perform a regular expression search and replace.
	 *
	 * @param  string          $subject
	 * @param  string|array    $pattern
	 * @param  string|callable $replacement
	 * @param  int             $limit
	 *
	 * @return string
	 * @throws RegexpException
	 */
	public function replace(string $subject, $pattern, $replacement = null, int $limit = -1): string
	{
		if (is_object($replacement) || is_array($replacement)) {
			if (!is_callable($replacement, false, $textual)) {
				throw new RegexpException("Callback '$textual' is not callable.");
			}

			return self::pcre('preg_replace_callback', [$pattern, $replacement, $subject, $limit]);

		} elseif ($replacement === null && is_array($pattern)) {
			$replacement = array_values($pattern);
			$pattern = array_keys($pattern);
		}

		return self::pcre('preg_replace', [$pattern, $replacement, $subject, $limit]);
	}

	/**
	 * @param string $func
	 * @param array  $args
	 *
	 * @return mixed
	 * @throws RegexpException
	 * @internal
	 */
	protected static function pcre(string $func, array $args)
	{
		static $messages = [
			PREG_INTERNAL_ERROR => 'Internal error',
			PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted',
			PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted',
			PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
			PREG_BAD_UTF8_OFFSET_ERROR => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point',
			PREG_JIT_STACKLIMIT_ERROR => 'Failed due to limited JIT stack space',
		];

		$res = $func(...$args);
		if (($code = preg_last_error()) // run-time error, but preg_last_error & return code are liars
			&& ($res === null || !in_array($func, ['preg_filter', 'preg_replace_callback', 'preg_replace'], true))
		) {
			throw new RegexpException(($messages[$code] ?? 'Unknown error')
				. ' (pattern: ' . implode(' or ', (array) $args[0]) . ')', $code);
		}

		return $res;
	}

}