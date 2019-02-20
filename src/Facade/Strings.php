<?php

namespace Hail\Facade;

/**
 * Class Strings
 *
 * @package Hail\Facade
 * @see \Hail\Util\Strings
 *
 * @method static bool checkEncoding(string $s)
 * @method static string fixEncoding(string $s)
 * @method static int ord(string $char)
 * @method static string chr(int $code)
 * @method static bool startsWith(string $haystack, string $needle)
 * @method static bool endsWith(string $haystack, string $needle)
 * @method static bool contains(string $haystack, array|string $needles)
 * @method static string normalize(string $s)
 * @method static string normalizeNewLines(string $s)
 * @method static string toAscii(string $s)
 * @method static string webalize(string $s, string $charList = null, bool $lower = true)
 * @method static string truncate(string $s, int $maxLen, string $append = "\xE2\x80\xA6")
 * @method static string indent(string $s, int $level = 1, string $chars = "\t")
 * @method static string firstLower(string $s)
 * @method static string firstUpper(string $s)
 * @method static bool compare(string $left, string $right, int $len = null)
 * @method static string findPrefix(string|array $first, ...$strings)
 * @method static string trim(string $s, string $charList = " \t\n\r\0\x0B\xC2\xA0")
 * @method static string ltrim(string $s, string $charList = " \t\n\r\0\x0B\xC2\xA0")
 * @method static string rtrim(string $s, string $charList = " \t\n\r\0\x0B\xC2\xA0")
 * @method static string padLeft(string $s, int $length, string $pad = ' ')
 * @method static string padRight(string $s, int $length, string $pad = ' ')
 * @method static string reverse(string $s)
 * @method static string|null before(string $haystack, string $needle, int $nth = 1)
 * @method static string|null after(string $haystack, string $needle, int $nth = 1)
 * @method static int|null indexOf(string $haystack, string $needle, int $nth = 1)
 * @method static array split(string $subject, string $pattern, int $flags = 0)
 * @method static array|null match(string $subject, string $pattern, int $flags = 0, int $offset = 0)
 * @method static array matchAll(string $subject, string $pattern, int $flags = 0, int $offset = 0)
 * @method static string replace(string $subject, string|array $pattern, string|callable $replacement = null, int $limit = -1)
 * @method static mixed pcre(string $func, array $args)
 * @method static bool isUTF8(string $string)
 * @method static void reset()
 * @method static void rules(string $type, array $rules, bool $reset = false)
 * @method static string pluralize(string $word)
 * @method static string singularize(string $word)
 * @method static string camelize(string $string, string $delimiter = '_')
 * @method static string underscore(string $string)
 * @method static string dasherize(string $string)
 * @method static string humanize(string $string, string $delimiter = '_')
 * @method static string delimit(string $string, string $delimiter = '_')
 * @method static string tableize(string $className)
 * @method static string classify(string $tableName)
 * @method static string variable(string $string)
 * @method static string slug(string $string, string $replacement = '-')
 */
class Strings extends Facade
{
}