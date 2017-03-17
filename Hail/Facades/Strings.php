<?php
namespace Hail\Facades;

/**
 * Class Strings
 *
 * @package Hail\Facades
 *
 * @method static bool checkEncoding(string $s)
 * @method static string fixEncoding(string $s)
 * @method static string chr(int $code)
 * @method static bool startsWith(string $haystack, string $needle)
 * @method static bool endsWith(string $haystack, string $needle)
 * @method static bool contains(string $haystack, string|array $needles)
 * @method static string normalize(string $s)
 * @method static string normalizeNewLines(string $s)
 * @method static string toAscii(string $s)
 * @method static string webalize(string $s, string $charList = null, bool $lower = true)
 * @method static string truncate(string $s, int $maxLen, string $append = "\xE2\x80\xA6")
 * @method static string indent(string $s, int $level = 1, string $chars = "\t")
 * @method static string firstLower(string $s)
 * @method static string firstUpper(string $s)
 * @method static bool compare(string $left, string $right, int $len = null)
 * @method static string findPrefix(...$strings)
 * @method static string trim(string $s, string $charList = \Hail\Util\Strings::TRIM_CHARACTERS)
 * @method static string padLeft(string $s, int $length, string $pad = ' ')
 * @method static string padRight(string $s, int $length, string $pad = ' ')
 * @method static string reverse(string $s)
 * @method static string|false before(string $haystack, string $needle, int $nth = 1)
 * @method static string|false after(string $haystack, string $needle, int $nth = 1)
 * @method static int|false indexOf(string $haystack, string $needle, int $nth = 1)
 * @method static array split(string $subject, string $pattern, int $flags = 0)
 * @method static mixed match(string $subject, string $pattern, int $flags = 0, int $offset = 0)
 * @method static array matchAll(string $subject, string $pattern, int $flags = 0, int $offset = 0)
 * @method static string replace(string $subject, string|array $pattern, string|array $replacement = null, int $limit = -1)
 */
class Strings extends Facade
{
	protected static $alias = \Hail\Util\Strings::class;

	protected static function instance()
	{
		return new static::$alias;
	}
}