<?php

namespace Hail\Util;

use Hail\Util\Exception\RegexpException;


/**
 * String tools library.
 *
 * @package Hail\Util
 * @author  Feng Hao <flyinghail@msn.com>
 *
 * @property-read array $irregular
 * @property-read array $plural
 * @property-read array $singular
 * @property-read array $transliteration
 * @property-read array $uninflected
 * @property-read bool  $classExistsNormalizer
 * @property-read bool  $classExistsTransliterator
 * @property-read bool  $funExistsMbOrd
 * @property-read bool  $funExistsMbChr
 * @property-read bool  $funExistsIcon
 */
class Strings
{
    public const TRIM_CHARACTERS = " \t\n\r\0\x0B\xC2\xA0";

    /**
     * Method cache array.
     *
     * @var array
     */
    protected $_cache = [];

    /**
     * The initial state of Inflector so reset() works.
     *
     * @var array
     */
    protected $_initialState = [];

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (\in_array($name, ['irregular', 'plural', 'singular', 'transliteration', 'uninflected'], true)) {
            return $this->$name = include __DIR__ . '/Data/' . $name . '.php';
        }

        switch ($name) {
            case 'classExistsNormalizer':
                return $this->classExistsNormalizer = \class_exists(\Normalizer::class, false);
            case 'classExistsTransliterator':
                return $this->classExistsTransliterator = \class_exists(\Transliterator::class, false);
            case 'funExistsMbOrd':
                return $this->funExistsMbOrd = \function_exists('\mb_ord');
            case 'funExistsMbChr':
                return $this->funExistsMbChr = \function_exists('\mb_chr');
            case 'funExistsIcon':
                return $this->funExistsIcon = \function_exists('\iconv');
            default:
                return null;
        }
    }

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
        return \htmlspecialchars_decode(\htmlspecialchars($s, ENT_NOQUOTES | ENT_IGNORE, 'UTF-8'), ENT_NOQUOTES);
    }

    /**
     * Get a decimal code representation of a specific character.
     *
     * @param   string $char Character.
     *
     * @return  int
     */
    public function ord(string $char): int
    {
        if ($this->funExistsMbOrd) {
            return \mb_ord($char);
        }

        $code = ($c = \unpack('C*', \substr($char, 0, 4))) ? $c[1] : 0;
        if (0xF0 <= $code) {
            return (($code - 0xF0) << 18) + (($c[2] - 0x80) << 12) + (($c[3] - 0x80) << 6) + $c[4] - 0x80;
        }
        if (0xE0 <= $code) {
            return (($code - 0xE0) << 12) + (($c[2] - 0x80) << 6) + $c[3] - 0x80;
        }
        if (0xC0 <= $code) {
            return (($code - 0xC0) << 6) + $c[2] - 0x80;
        }

        return $code;
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

        if ($this->funExistsMbChr) {
            return \mb_chr($code);
        }

        if ($this->funExistsIcon) {
            return \iconv('UTF-32BE', 'UTF-8//IGNORE', \pack('N', $code));
        }

        if (0x80 > $code %= 0x200000) {
            return \chr($code);
        }
        if (0x800 > $code) {
            return \chr(0xC0 | $code >> 6) . \chr(0x80 | $code & 0x3F);
        }
        if (0x10000 > $code) {
            return \chr(0xE0 | $code >> 12) . \chr(0x80 | $code >> 6 & 0x3F) . \chr(0x80 | $code & 0x3F);
        }

        return \chr(0xF0 | $code >> 18) . \chr(0x80 | $code >> 12 & 0x3F) . \chr(0x80 | $code >> 6 & 0x3F) . \chr(0x80 | $code & 0x3F);
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
        return \strpos($haystack, $needle) === 0;
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
        return $needle === '' || \substr($haystack, -\strlen($needle)) === $needle;
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
            if ($needle !== '' && \mb_strpos($haystack, $needle) !== false) {
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
        if ($this->classExistsNormalizer) {
            // convert to compressed normal form (NFC)
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C);
        }

        $s = $this->normalizeNewLines($s);

        // remove control characters; leave \t + \n
        $s = \preg_replace('#[\x00-\x08\x0B-\x1F\x7F-\x9F]+#u', '', $s);

        // right trim
        $s = \preg_replace('#[\t ]+$#m', '', $s);

        // leading and trailing blank lines
        $s = \trim($s, "\n");

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
        if (\strpos($s, "\r") === false) {
            return $s;
        }

        return \str_replace(["\r\n", "\r"], "\n", $s);
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
        if (0 === \preg_match('#[\x80-\xff]#', $s)) {
            return $s;
        }

        if (!$this->classExistsTransliterator) {
            throw new \RuntimeException('Intl extension not loaded');
        }

        static $transliterator = null;
        if ($transliterator === null) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII');
        }

        $s = \preg_replace('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{2FF}\x{370}-\x{10FFFF}]#u', '', $s);
        $s = \strtr($s, '`\'"^~?', "\x01\x02\x03\x04\x05\x06");
        $s = \str_replace(
            ["\u{201E}", "\u{201C}", "\u{201D}", "\u{201A}", "\u{2018}", "\u{2019}", "\u{B0}"],
            ["\x03", "\x03", "\x03", "\x02", "\x02", "\x02", "\x04"], $s
        );
        $s = $transliterator->transliterate($s);
        if (\ICONV_IMPL === 'glibc') {
            $s = \str_replace(
                ["\u{BB}", "\u{AB}", "\u{2026}", "\u{2122}", "\u{A9}", "\u{AE}"],
                ['>>', '<<', '...', 'TM', '(c)', '(R)'], $s
            );
            $s = \iconv('UTF-8', 'WINDOWS-1250//TRANSLIT//IGNORE', $s);
            $s = \strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e"
                . "\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3"
                . "\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8"
                . "\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe"
                . "\x96\xa0\x8b\x97\x9b\xa6\xad\xb7",
                'ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt- <->|-.');
            $s = \preg_replace('#[^\x00-\x7F]++#', '', $s);
        } else {
            $s = \iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        }
        $s = \str_replace(['`', "'", '"', '^', '~', '?'], '', $s);

        return \strtr($s, "\x01\x02\x03\x04\x05\x06", '`\'"^~?');
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
            $s = \strtolower($s);
        }
        $s = \preg_replace('#[^a-z0-9' . ($charList !== null ? \preg_quote($charList, '#') : '') . ']+#i', '-', $s);
        $s = \trim($s, '-');

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
        if (\mb_strlen($s) > $maxLen) {
            $maxLen -= \mb_strlen($append);
            if ($maxLen < 1) {
                return $append;
            }

            if ($matches = $this->match($s, '#^.{1,' . $maxLen . '}(?=[\s\x00-/:-@\[-`{-~])#us')) {
                return $matches[0] . $append;
            }

            return \mb_substr($s, 0, $maxLen) . $append;
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
            $s = $this->replace($s, '#(?:^|[\r\n]+)(?=[^\r\n])#', '$0' . \str_repeat($chars, $level));
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
    public function firstLower(string $s): string
    {
        return \mb_strtolower(\mb_substr($s, 0, 1)) . \mb_substr($s, 1);
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
        return \mb_strtoupper(\mb_substr($s, 0, 1)) . \mb_substr($s, 1);
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
    public function compare(string $left, string $right, int $len = null): bool
    {
        if ($this->classExistsNormalizer) {
            $left = \Normalizer::normalize($left, \Normalizer::FORM_D); // form NFD is faster
            $right = \Normalizer::normalize($right, \Normalizer::FORM_D); // form NFD is faster
        }

        if ($len < 0) {
            $left = \mb_substr($left, $len, -$len);
            $right = \mb_substr($right, $len, -$len);
        } elseif ($len !== null) {
            $left = \mb_substr($left, 0, $len);
            $right = \mb_substr($right, 0, $len);
        }

        return \mb_strtolower($left) === \mb_strtolower($right);
    }


    /**
     * Finds the length of common prefix of strings.
     *
     * @param string|array $first
     * @param array        ...$strings
     *
     * @return string
     */
    public function findPrefix($first, ...$strings): string
    {
        if (\is_array($first)) {
            $strings = $first;
            $first = $strings[0];
            unset($strings[0]);
        }

        for ($i = 0, $n = \strlen($first); $i < $n; $i++) {
            foreach ($strings as $s) {
                if (!isset($s[$i]) || $first[$i] !== $s[$i]) {
                    while ($i && $first[$i - 1] >= "\x80" && $first[$i] >= "\x80" && $first[$i] < "\xC0") {
                        $i--;
                    }

                    return \substr($first, 0, $i);
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
        $charList = \preg_quote($charList, '#');

        return $this->replace($s, '#^[' . $charList . ']+|[' . $charList . ']+\z#u', '');
    }

    /**
     * Strips whitespace from the beginning of a string.
     *
     * @param  string $s UTF-8 encoding
     * @param  string $charList
     *
     * @return string
     * @throws RegexpException
     */
    public function ltrim(string $s, string $charList = self::TRIM_CHARACTERS): string
    {
        $charList = \preg_quote($charList, '#');

        return $this->replace($s, '#^[' . $charList . ']+#u', '');
    }

    /**
     * Strips whitespace from the end of a string.
     *
     * @param  string $s UTF-8 encoding
     * @param  string $charList
     *
     * @return string
     * @throws RegexpException
     */
    public function rtrim(string $s, string $charList = self::TRIM_CHARACTERS): string
    {
        $charList = \preg_quote($charList, '#');

        return $this->replace($s, '#[' . $charList . ']+\z#u', '');
    }


    /**
     * Pad a UTF-8 string to a certain length with another string.
     *
     * @param  string $s UTF-8 encoding
     * @param  int    $length
     * @param  string $pad
     *
     * @return string
     */
    public function padLeft(string $s, int $length, string $pad = ' '): string
    {
        $length -= \mb_strlen($s);
        if ($length <= 0) {
            return $s;
        }

        $padLen = \mb_strlen($pad);

        return \str_repeat($pad, (int) ($length / $padLen)) . \mb_substr($pad, 0, $length % $padLen) . $s;
    }


    /**
     * Pad a UTF-8 string to a certain length with another string.
     *
     * @param string $s UTF-8 encoding
     * @param int    $length
     * @param string $pad
     *
     * @return string
     */
    public function padRight(string $s, int $length, string $pad = ' '): string
    {
        $length -= \mb_strlen($s);
        if ($length <= 0) {
            return $s;
        }

        $padLen = \mb_strlen($pad);

        return $s . \str_repeat($pad, (int) ($length / $padLen)) . \mb_substr($pad, 0, $length % $padLen);
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
        if ($this->funExistsIcon) {
            return \iconv('UTF-32LE', 'UTF-8', \strrev(\iconv('UTF-8', 'UTF-32BE', $s)));
        }

        \preg_match_all('/./us', $s, $ar);

        return \implode('', \array_reverse($ar[0]));
    }


    /**
     * Returns part of $haystack before $nth occurence of $needle (negative value means searching from the end).
     *
     * @param  string $haystack
     * @param  string $needle
     * @param  int    $nth negative value means searching from the end
     *
     * @return string|null  returns FALSE if the needle was not found
     */
    public function before(string $haystack, string $needle, int $nth = 1): ?string
    {
        $pos = $this->pos($haystack, $needle, $nth);

        return $pos === null
            ? null
            : \substr($haystack, 0, $pos);
    }


    /**
     * Returns part of $haystack after $nth occurence of $needle (negative value means searching from the end).
     *
     * @param  string $haystack
     * @param  string $needle
     * @param  int    $nth negative value means searching from the end
     *
     * @return string|null  returns FALSE if the needle was not found
     */
    public function after(string $haystack, string $needle, int $nth = 1): ?string
    {
        $pos = $this->pos($haystack, $needle, $nth);

        return $pos === null
            ? null
            : \substr($haystack, $pos + \strlen($needle));
    }


    /**
     * Returns position of $nth occurence of $needle in $haystack.
     *
     * @param  string $haystack
     * @param  string $needle
     * @param  int    $nth negative value means searching from the end
     *
     * @return int|null  offset in characters or FALSE if the needle was not found
     */
    public function indexOf(string $haystack, string $needle, int $nth = 1): ?int
    {
        $pos = $this->pos($haystack, $needle, $nth);

        return $pos === null
            ? null
            : \mb_strlen(\substr($haystack, 0, $pos));
    }

    /**
     * Returns position of $nth occurence of $needle in $haystack.
     *
     * @param string $haystack
     * @param string $needle
     * @param int    $nth
     *
     * @return int|null  offset in bytes or FALSE if the needle was not found
     */
    private function pos(string $haystack, string $needle, $nth = 1): ?int
    {
        if (!$nth) {
            return null;
        }

        if ($nth > 0) {
            if ($needle === '') {
                return 0;
            }
            $pos = 0;
            while (false !== ($pos = \strpos($haystack, $needle, $pos)) && --$nth) {
                $pos++;
            }
        } else {
            $len = \strlen($haystack);
            if ($needle === '') {
                return $len;
            }
            $pos = $len - 1;
            while (false !== ($pos = \strrpos($haystack, $needle, $pos - $len)) && ++$nth) {
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
        return $this->pcre('\preg_split', [$pattern, $subject, -1, $flags | PREG_SPLIT_DELIM_CAPTURE]);
    }


    /**
     * Performs a regular expression match.
     *
     * @param  string $subject
     * @param  string $pattern
     * @param  int    $flags  can be PREG_OFFSET_CAPTURE (returned in bytes)
     * @param  int    $offset offset in bytes
     *
     * @return array|null
     * @throws RegexpException
     */
    public function match(string $subject, string $pattern, int $flags = 0, int $offset = 0): ?array
    {
        if ($offset > \strlen($subject)) {
            return null;
        }

        $m = null;

        return $this->pcre('\preg_match', [$pattern, $subject, &$m, $flags, $offset])
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
        if ($offset > \strlen($subject)) {
            return [];
        }

        $m = null;
        $this->pcre('\preg_match_all', [
            $pattern,
            $subject,
            &$m,
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
        if (\is_object($replacement) || \is_array($replacement)) {
            if (!\is_callable($replacement, false, $textual)) {
                throw new RegexpException("Callback '$textual' is not callable.");
            }

            return $this->pcre('\preg_replace_callback', [$pattern, $replacement, $subject, $limit]);

        }

        if ($replacement === null && \is_array($pattern)) {
            $replacement = \array_values($pattern);
            $pattern = \array_keys($pattern);
        }

        return $this->pcre('\preg_replace', [$pattern, $replacement, $subject, $limit]);
    }

    /**
     * @param string $func
     * @param array  $args
     *
     * @return mixed
     * @throws RegexpException
     * @internal
     */
    public function pcre(string $func, array $args)
    {
        static $messages = [
            PREG_INTERNAL_ERROR => 'Internal PCRE error',
            PREG_BACKTRACK_LIMIT_ERROR => 'pcre.backtrack_limit was exhausted',
            PREG_RECURSION_LIMIT_ERROR => 'pcre.recursion_limit was exhausted',
            PREG_BAD_UTF8_ERROR => 'Malformed UTF-8 data',
            PREG_BAD_UTF8_OFFSET_ERROR => 'Offset didn\'t correspond to the begin of a valid UTF-8 code point',
            PREG_JIT_STACKLIMIT_ERROR => 'Failed due to limited JIT stack space',
        ];

        $res = $func(...$args);
        if (($code = \preg_last_error()) // run-time error, but preg_last_error & return code are liars
            && ($res === null || !\in_array($func, ['\preg_filter', '\preg_replace_callback', '\preg_replace'], true))
        ) {
            throw new RegexpException(($messages[$code] ?? 'Unknown error')
                . ' (pattern: ' . \implode(' or ', (array) $args[0]) . ')', $code);
        }

        return $res;
    }

    /**
     * Check if a string is encoded in UTF-8.
     *
     * @param   string $string String.
     *
     * @return  bool
     */
    public function isUTF8(string $string): bool
    {
        return (bool) \preg_match('##u', $string);
    }

    /**
     * Cache inflected values, and return if already available
     *
     * @param string      $type  Inflection type
     * @param string      $key   Original value
     * @param string|null $value Inflected value
     *
     * @return string|null Inflected value on cache hit or false on cache miss.
     */
    protected function _cache(string $type, string $key, string $value = null): ?string
    {
        $key = '_' . $key;
        $type = '_' . $type;
        if ($value !== null) {
            $this->_cache[$type][$key] = $value;

            return $value;
        }

        return $this->_cache[$type][$key] ?? null;
    }

    /**
     * Clears inflected value caches.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->_cache = [];

        foreach (['irregular', 'plural', 'singular', 'transliteration', 'uninflected'] as $name) {
            if (isset($this->$name)) {
                $this->$name = include __DIR__ . '/Data/' . $name . '.php';
            }
        }
    }

    /**
     * Adds custom inflection $rules, of either 'plural', 'singular',
     * 'uninflected', 'irregular' or 'transliteration' $type.
     *
     * ### Usage:
     *
     * ```
     * Inflector::rules('plural', ['/^(inflect)or$/i' => '\1ables']);
     * Inflector::rules('irregular', ['red' => 'redlings']);
     * Inflector::rules('uninflected', ['dontinflectme']);
     * Inflector::rules('transliteration', ['/å/' => 'aa']);
     * ```
     *
     * @param string $type  The type of inflection, either 'plural', 'singular',
     *                      'uninflected' or 'transliteration'.
     * @param array  $rules Array of rules to be added.
     * @param bool   $reset If true, will unset default inflections for all
     *                      new rules that are being defined in $rules.
     *
     * @return void
     */
    public function rules(string $type, array $rules, bool $reset = false): void
    {
        $var = '_' . $type;

        if ($reset) {
            $this->${$var} = $rules;
        } elseif ($type === 'uninflected') {
            $this->uninflected = \array_merge(
                $rules,
                $this->uninflected
            );
        } else {
            $this->${$var} = \array_merge($this->${$var}, $rules);
        }

        $this->_cache = [];
    }

    /**
     * Return $word in plural form.
     *
     * @param string $word Word in singular
     *
     * @return string Word in plural
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-plural-singular-forms
     */
    public function pluralize(string $word): string
    {
        if (isset($this->_cache['pluralize'][$word])) {
            return $this->_cache['pluralize'][$word];
        }

        if (!isset($this->_cache['irregular']['pluralize'])) {
            $this->_cache['irregular']['pluralize'] = '(?:' . \implode('|', \array_keys($this->irregular)) . ')';
        }

        if (\preg_match('/(.*?(?:\\b|_))(' . $this->_cache['irregular']['pluralize'] . ')$/i', $word, $regs)) {
            $this->_cache['pluralize'][$word] = $regs[1] . $regs[2][0] .
                \substr($this->irregular[\strtolower($regs[2])], 1);

            return $this->_cache['pluralize'][$word];
        }

        if (!isset($this->_cache['uninflected'])) {
            $this->_cache['uninflected'] = '(?:' . \implode('|', $this->uninflected) . ')';
        }

        if (\preg_match('/^(' . $this->_cache['uninflected'] . ')$/i', $word, $regs)) {
            $this->_cache['pluralize'][$word] = $word;

            return $word;
        }

        foreach ($this->plural as $rule => $replacement) {
            if (\preg_match($rule, $word)) {
                $this->_cache['pluralize'][$word] = \preg_replace($rule, $replacement, $word);

                return $this->_cache['pluralize'][$word];
            }
        }

        return $word;
    }

    /**
     * Return $word in singular form.
     *
     * @param string $word Word in plural
     *
     * @return string Word in singular
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-plural-singular-forms
     */
    public function singularize(string $word): string
    {
        if (isset($this->_cache['singularize'][$word])) {
            return $this->_cache['singularize'][$word];
        }

        if (!isset($this->_cache['irregular']['singular'])) {
            $this->_cache['irregular']['singular'] = '(?:' . \implode('|', $this->irregular) . ')';
        }

        if (\preg_match('/(.*?(?:\\b|_))(' . $this->_cache['irregular']['singular'] . ')$/i', $word, $regs)) {
            $this->_cache['singularize'][$word] = $regs[1] . $regs[2][0] .
                \substr(\array_search(\strtolower($regs[2]), $this->irregular, true), 1);

            return $this->_cache['singularize'][$word];
        }

        if (!isset($this->_cache['uninflected'])) {
            $this->_cache['uninflected'] = '(?:' . \implode('|', $this->uninflected) . ')';
        }

        if (\preg_match('/^(' . $this->_cache['uninflected'] . ')$/i', $word, $regs)) {
            $this->_cache['pluralize'][$word] = $word;

            return $word;
        }

        foreach ($this->singular as $rule => $replacement) {
            if (\preg_match($rule, $word)) {
                $this->_cache['singularize'][$word] = \preg_replace($rule, $replacement, $word);

                return $this->_cache['singularize'][$word];
            }
        }
        $this->_cache['singularize'][$word] = $word;

        return $word;
    }

    /**
     * Returns the input lower_case_delimited_string as a CamelCasedString.
     *
     * @param string $string    String to camelize
     * @param string $delimiter the delimiter in the input string
     *
     * @return string CamelizedStringLikeThis.
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-camelcase-and-under-scored-forms
     */
    public function camelize(string $string, string $delimiter = '_'): string
    {
        $cacheKey = __FUNCTION__ . $delimiter;

        $result = $this->_cache($cacheKey, $string);

        if ($result === null) {
            $result = \str_replace(' ', '', $this->humanize($string, $delimiter));
            $this->_cache($cacheKey, $string, $result);
        }

        return $result;
    }

    /**
     * Returns the input CamelCasedString as an underscored_string.
     *
     * Also replaces dashes with underscores
     *
     * @param string $string CamelCasedString to be "underscorized"
     *
     * @return string underscore_version of the input string
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-camelcase-and-under-scored-forms
     */
    public function underscore(string $string): string
    {
        return $this->delimit(\str_replace('-', '_', $string), '_');
    }

    /**
     * Returns the input CamelCasedString as an dashed-string.
     *
     * Also replaces underscores with dashes
     *
     * @param string $string The string to dasherize.
     *
     * @return string Dashed version of the input string
     */
    public function dasherize(string $string): string
    {
        return $this->delimit(\str_replace('_', '-', $string), '-');
    }

    /**
     * Returns the input lower_case_delimited_string as 'A Human Readable String'.
     * (Underscores are replaced by spaces and capitalized following words.)
     *
     * @param string $string    String to be humanized
     * @param string $delimiter the character to replace with a space
     *
     * @return string Human-readable string
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-human-readable-forms
     */
    public function humanize(string $string, string $delimiter = '_'): string
    {
        $cacheKey = __FUNCTION__ . $delimiter;

        $result = $this->_cache($cacheKey, $string);

        if ($result === null) {
            $result = \mb_convert_case(\str_replace($delimiter, ' ', $string), MB_CASE_TITLE);
            $this->_cache($cacheKey, $string, $result);
        }

        return $result;
    }

    /**
     * Expects a CamelCasedInputString, and produces a lower_case_delimited_string
     *
     * @param string $string    String to delimit
     * @param string $delimiter the character to use as a delimiter
     *
     * @return string delimited string
     */
    public function delimit(string $string, string $delimiter = '_'): string
    {
        $cacheKey = __FUNCTION__ . $delimiter;

        $result = $this->_cache($cacheKey, $string);

        if ($result === null) {
            $result = \mb_strtolower(\preg_replace('/(?<=\\w)([A-Z])/', $delimiter . '\\1', $string));
            $this->_cache($cacheKey, $string, $result);
        }

        return $result;
    }

    /**
     * Returns corresponding table name for given model $className. ("people" for the model class "Person").
     *
     * @param string $className Name of class to get database table name for
     *
     * @return string Name of the database table for given class
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-table-and-class-name-forms
     */
    public function tableize(string $className): string
    {
        $result = $this->_cache(__FUNCTION__, $className);

        if ($result === null) {
            $result = $this->pluralize($this->underscore($className));
            $this->_cache(__FUNCTION__, $className, $result);
        }

        return $result;
    }

    /**
     * Returns Cake model class name ("Person" for the database table "people".) for given database table.
     *
     * @param string $tableName Name of database table to get class name for
     *
     * @return string Class name
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-table-and-class-name-forms
     */
    public function classify(string $tableName): string
    {
        $result = $this->_cache(__FUNCTION__, $tableName);

        if ($result === null) {
            $result = $this->camelize($this->singularize($tableName));
            $this->_cache(__FUNCTION__, $tableName, $result);
        }

        return $result;
    }

    /**
     * Returns camelBacked version of an underscored string.
     *
     * @param string $string String to convert.
     *
     * @return string in variable form
     * @link http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-variable-names
     */
    public function variable(string $string): string
    {
        $result = $this->_cache(__FUNCTION__, $string);

        if ($result === null) {
            $camelized = $this->camelize($this->underscore($string));
            $replace = \strtolower($camelized[0]);
            $result = $replace . \substr($camelized, 1);
            $this->_cache(__FUNCTION__, $string, $result);
        }

        return $result;
    }

    /**
     * Returns a string with all spaces converted to dashes (by default), accented
     * characters converted to non-accented characters, and non word characters removed.
     *
     * @deprecated 3.2.7 Use Text::slug() instead.
     *
     * @param string $string      the string you want to slug
     * @param string $replacement will replace keys in map
     *
     * @return string
     * @link       http://book.cakephp.org/3.0/en/core-libraries/inflector.html#creating-url-safe-strings
     */
    public function slug(string $string, string $replacement = '-'): string
    {
        $quotedReplacement = \preg_quote($replacement, '/');
        $string = \strtr($string, $this->transliteration);

        return \preg_replace([
            '/[^\s\p{Zs}\p{Ll}\p{Lm}\p{Lo}\p{Lt}\p{Lu}\p{Nd}]/mu',
            '/[\s\p{Zs}]+/mu',
            '/^[' . $quotedReplacement . ']+|[' . $quotedReplacement . ']+$/',
        ], [' ', $replacement, ''], $string);
    }
}