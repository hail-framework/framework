<?php
/**
 * Hoa
 *
 *
 * @license
 *
 * New BSD License
 *
 * Copyright © 2007-2017, Hoa community. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of the Hoa nor the names of its contributors may be
 *       used to endorse or promote products derived from this software without
 *       specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDERS AND CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

namespace Hail\Util;

use Hail\Util\Exception\RegexpException;

/**
 * This class represents a UTF-8 string.
 * Please, see:
 *     • http://www.ietf.org/rfc/rfc3454.txt;
 *     • http://unicode.org/reports/tr9/;
 *     • http://www.unicode.org/Public/6.0.0/ucd/UnicodeData.txt.
 *
 * @copyright  Copyright © 2007-2017 Hoa community
 * @license    New BSD License
 */
class Utf8String implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * Left-To-Right.
     *
     * @const int
     */
    public const LTR = 0;

    /**
     * Right-To-Left.
     *
     * @const int
     */
    public const RTL = 1;

    /**
     * ZERO WIDTH NON-BREAKING SPACE (ZWNPBSP, aka byte-order mark, BOM).
     *
     * @const int
     */
    public const BOM = 0xfeff;

    /**
     * LEFT-TO-RIGHT MARK.
     *
     * @const int
     */
    public const LRM = 0x200e;

    /**
     * RIGHT-TO-LEFT MARK.
     *
     * @const int
     */
    public const RLM = 0x200f;

    /**
     * LEFT-TO-RIGHT EMBEDDING.
     *
     * @const int
     */
    public const LRE = 0x202a;

    /**
     * RIGHT-TO-LEFT EMBEDDING.
     *
     * @const int
     */
    public const RLE = 0x202b;

    /**
     * POP DIRECTIONAL FORMATTING.
     *
     * @const int
     */
    public const PDF = 0x202c;

    /**
     * LEFT-TO-RIGHT OVERRIDE.
     *
     * @const int
     */
    public const LRO = 0x202d;

    /**
     * RIGHT-TO-LEFT OVERRIDE.
     *
     * @const int
     */
    public const RLO = 0x202e;

    /**
     * Split: non-empty pieces is returned.
     *
     * @const int
     */
    public const WITHOUT_EMPTY = PREG_SPLIT_NO_EMPTY;

    /**
     * Split: parenthesized expression in the delimiter pattern will be captured
     * and returned.
     *
     * @const int
     */
    public const WITH_DELIMITERS = PREG_SPLIT_DELIM_CAPTURE;

    /**
     * Split: offsets of captures will be returned.
     *
     * @const int
     */
    public const WITH_OFFSET = 260; //   PREG_OFFSET_CAPTURE
    // | PREG_SPLIT_OFFSET_CAPTURE

    /**
     * Group results by patterns.
     *
     * @const int
     */
    public const GROUP_BY_PATTERN = PREG_PATTERN_ORDER;

    /**
     * Group results by tuple (set of patterns).
     *
     * @const int
     */
    public const GROUP_BY_TUPLE = PREG_SET_ORDER;

    /**
     * Current string.
     *
     * @var string
     */
    protected $_string;

    /**
     * Direction. Please see self::LTR and self::RTL constants.
     *
     * @var int
     */
    protected $_direction;

    /**
     * @var Strings
     */
    private $strings;

    /**
     * Construct a UTF-8 string.
     *
     * @param   string $string String.
     */
    public function __construct(string $string = null)
    {
        if (null !== $string) {
            $this->append($string);
        }

        $this->strings = Strings::getInstance();
    }

    /**
     * Append a substring to the current string, i.e. add to the end.
     *
     * @param   string $substring Substring to append.
     *
     * @return  self
     */
    public function append(string $substring)
    {
        $this->_string .= $substring;

        return $this;
    }

    /**
     * Prepend a substring to the current string, i.e. add to the start.
     *
     * @param   string $substring Substring to append.
     *
     * @return  self
     */
    public function prepend(string $substring)
    {
        $this->_string = $substring . $this->_string;

        return $this;
    }

    /**
     * Pad the current string to a certain length with another piece, aka piece.
     *
     * @param   int    $length     Length.
     * @param   string $piece      Piece.
     * @param   int    $side       Whether we append at the end or the beginning
     *                             of the current string.
     *
     * @return  self
     */
    public function pad(int $length, string $piece = ' ', int $side = STR_PAD_RIGHT)
    {
        if ($side === STR_PAD_BOTH) {
            $length -= $this->count();

            if ($length <= 0) {
                return $this;
            }

            $left = (int) ($length / 2);
            $right = $length - $left;

            $this->_string = $this->strings->padRight(
                $this->strings->padLeft($this->_string, $left, $piece),
                $right, $piece
            );
        } elseif (STR_PAD_LEFT === $side) {
            $this->_string = $this->strings->padLeft($this->_string, $length, $piece);
        } else {
            $this->_string = $this->strings->padRight($this->_string, $length, $piece);
        }

        return $this;
    }

    /**
     * Make a comparison with a string.
     * Return < 0 if current string is less than $string, > 0 if greater and 0
     * if equal.
     *
     * @param   mixed $string String.
     *
     * @return  int
     */
    public function compare($string)
    {
        if (\class_exists('\Collator', false)) {
            static $collator = null;
            if ($collator === null) {
                $collator = new \Collator(\setlocale(LC_COLLATE, null));
            }

            return $collator->compare($this->_string, $string);
        }

        return \strcmp($this->_string, (string) $string);
    }


    /**
     * Ensure that the pattern is safe for Unicode: add the “u” option.
     *
     * @param   string $pattern Pattern.
     *
     * @return  string
     */
    public function safePattern(string $pattern): string
    {
        $delimiter = \mb_substr($pattern, 0, 1);
        $options = \mb_substr(
            \mb_strrchr($pattern, $delimiter, false),
            \mb_strlen($delimiter)
        );

        if (false === \strpos($options, 'u')) {
            $pattern .= 'u';
        }

        return $pattern;
    }

    /**
     * Perform a regular expression (PCRE) match.
     *
     * @param   string $pattern     Pattern.
     * @param   int    $flags       Please, see constants self::WITH_OFFSET,
     *                              self::GROUP_BY_PATTERN and
     *                              self::GROUP_BY_TUPLE.
     * @param   int    $offset      Alternate place from which to start the
     *
     * @return  array|null
     * @throws RegexpException
     */
    public function match(
        $pattern,
        $flags = 0,
        $offset = 0
    ) {
        $pattern = $this->safePattern($pattern);

        if (0 !== $flags) {
            $flags &= ~PREG_SPLIT_OFFSET_CAPTURE;
        }

        return $this->strings->match($this->_string, $pattern, $flags, $offset);
    }

    /**
     * Perform a global regular expression (PCRE) match.
     *
     * @param   string $pattern     Pattern.
     * @param   int    $flags       Please, see constants self::WITH_OFFSET,
     *                              self::GROUP_BY_PATTERN and
     *                              self::GROUP_BY_TUPLE.
     * @param   int    $offset      Alternate place from which to start the
     *                              search.
     *
     * @return  array
     * @throws RegexpException
     */
    public function matchAll(
        $pattern,
        $flags = 0,
        $offset = 0
    ) {
        $pattern = $this->safePattern($pattern);

        if (0 === $flags) {
            $flags = static::GROUP_BY_PATTERN;
        }

        return $this->strings->matchAll($this->_string, $pattern, $flags, $offset);
    }

    /**
     * Perform a regular expression (PCRE) search and replace.
     *
     * @param   mixed $pattern          Pattern(s).
     * @param   mixed $replacement      Replacement(s) (please, see
     *                                  preg_replace() documentation).
     * @param   int   $limit            Maximum of replacements. -1 for unbound.
     *
     * @return  self
     * @throws RegexpException
     */
    public function replace($pattern, $replacement, $limit = -1)
    {
        $pattern = $this->safePattern($pattern);
        $this->_string = $this->strings->replace($this->_string, $pattern, $replacement, $limit);

        return $this;
    }

    /**
     * Split the current string according to a given pattern (PCRE).
     *
     * @param   string $pattern     Pattern (as a regular expression).
     * @param   int    $flags       Please, see constants self::WITHOUT_EMPTY,
     *                              self::WITH_DELIMITERS, self::WITH_OFFSET.
     *
     * @return  array
     * @throws RegexpException
     */
    public function split(
        $pattern,
        $flags = self::WITHOUT_EMPTY
    ): array {
        $pattern = $this->safePattern($pattern);

        return $this->strings->split($this->_string, $pattern, $flags);
    }

    /**
     * Iterator over chars.
     *
     * @return  \Traversable
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(\preg_split('#(?<!^)(?!$)#u', $this->_string));
    }

    /**
     * Perform a lowercase folding on the current string.
     *
     * @return  self
     */
    public function toLowerCase()
    {
        $this->_string = \mb_strtolower($this->_string);

        return $this;
    }

    /**
     * Perform an uppercase folding on the current string.
     *
     * @return  self
     */
    public function toUpperCase()
    {
        $this->_string = \mb_strtoupper($this->_string);

        return $this;
    }

    /**
     * Transform a UTF-8 string into an ASCII one.
     *
     * @return  self
     * @throws  \Exception
     */
    public function toAscii()
    {
        if (0 === \preg_match('#[\x80-\xff]#', $this->_string)) {
            return $this;
        }

        $this->_string = $this->strings->toAscii($this->_string);

        return $this;
    }

    /**
     * Transliterate the string into another.
     * See http://userguide.icu-project.org/transforms/general for $identifier.
     *
     * @param   string $identifier Identifier.
     * @param   int    $start      Start.
     * @param   int    $end        End.
     *
     * @return  self
     * @throws  \RuntimeException
     */
    public function transliterate($identifier, $start = 0, $end = null)
    {
        if (null === $transliterator = \Transliterator::create($identifier)) {
            throw new \RuntimeException(__METHOD__ . ' needs the class Transliterator to work properly.');
        }

        $this->_string = $transliterator->transliterate($this->_string, $start, $end);

        return $this;
    }

    /**
     * Strip characters (default \s) of the current string.
     *
     * @param   string $regex Characters to remove.
     *
     * @return  self
     * @throws RegexpException
     */
    public function trim($regex = Strings::TRIM_CHARACTERS)
    {
        $this->_string = $this->strings->trim($this->_string, $regex);
        $this->_direction = null;

        return $this;
    }

    public function ltrim($regex = Strings::TRIM_CHARACTERS)
    {
        $this->_string = $this->strings->ltrim($this->_string, $regex);
        $this->_direction = null;

        return $this;
    }

    public function rtrim($regex = Strings::TRIM_CHARACTERS)
    {
        $this->_string = $this->strings->rtrim($this->_string, $regex);
        $this->_direction = null;

        return $this;
    }

    /**
     * Compute offset (negative, unbound etc.).
     *
     * @param   int $offset Offset.
     *
     * @return  int
     */
    protected function computeOffset($offset)
    {
        $length = \mb_strlen($this->_string);

        if (0 > $offset) {
            $offset = -$offset % $length;

            if (0 !== $offset) {
                $offset = $length - $offset;
            }
        } elseif ($offset >= $length) {
            $offset %= $length;
        }

        return $offset;
    }

    /**
     * Get a specific chars of the current string.
     *
     * @param   int $offset Offset (can be negative and unbound).
     *
     * @return  string
     */
    public function offsetGet($offset)
    {
        return \mb_substr($this->_string, $this->computeOffset($offset), 1);
    }

    /**
     * Set a specific character of the current string.
     *
     * @param   int    $offset Offset (can be negative and unbound).
     * @param   string $value  Value.
     *
     * @return  self
     */
    public function offsetSet($offset, $value)
    {
        $head = null;
        $offset = $this->computeOffset($offset);

        if (0 < $offset) {
            $head = \mb_substr($this->_string, 0, $offset);
        }

        $tail = \mb_substr($this->_string, $offset + 1);
        $this->_string = $head . $value . $tail;
        $this->_direction = null;

        return $this;
    }

    /**
     * Delete a specific character of the current string.
     *
     * @param   int $offset Offset (can be negative and unbound).
     *
     * @return  string
     */
    public function offsetUnset($offset)
    {
        return $this->offsetSet($offset, null);
    }

    /**
     * Check if a specific offset exists.
     *
     * @return  bool
     */
    public function offsetExists($offset)
    {
        if (!\is_int($offset)) {
            return false;
        }

        $len = $this->count();

        return $offset >= -$len && $offset < $len;
    }

    /**
     * Reduce the strings.
     *
     * @param   int $start  Position of first character.
     * @param   int $length Maximum number of characters.
     *
     * @return  self
     */
    public function reduce($start, $length = null)
    {
        $this->_string = \mb_substr($this->_string, $start, $length);

        return $this;
    }

    /**
     * Count number of characters of the current string.
     *
     * @return  int
     */
    public function count()
    {
        return \mb_strlen($this->_string);
    }

    /**
     * Get byte (not character) at a specific offset.
     *
     * @param   int $offset Offset (can be negative and unbound).
     *
     * @return  string
     */
    public function getByteAt($offset)
    {
        $length = \strlen($this->_string);

        if (0 > $offset) {
            $offset = -$offset % $length;

            if (0 !== $offset) {
                $offset = $length - $offset;
            }
        } elseif ($offset >= $length) {
            $offset %= $length;
        }

        return $this->_string[$offset];
    }

    /**
     * Count number of bytes (not characters) of the current string.
     *
     * @return  int
     */
    public function getBytesLength()
    {
        return \strlen($this->_string);
    }

    /**
     * Get the width of the current string.
     * Useful when printing the string in monotype (some character need more
     * than one column to be printed).
     *
     * @return  int
     */
    public function getWidth()
    {
        return \mb_strwidth($this->_string);
    }

    /**
     * Get direction of the current string.
     * Please, see the self::LTR and self::RTL constants.
     * It does not yet support embedding directions.
     *
     * @return  int
     */
    public function getDirection()
    {
        if (null === $this->_direction) {
            if (null === $this->_string) {
                $this->_direction = static::LTR;
            } else {
                $this->_direction = $this->getCharDirection(
                    \mb_substr($this->_string, 0, 1)
                );
            }
        }

        return $this->_direction;
    }

    /**
     * Get character of a specific character.
     * Please, see the self::LTR and self::RTL constants.
     *
     * @param   string $char Character.
     *
     * @return  int
     */
    public function getCharDirection(string $char): int
    {
        $c = $this->strings->ord($char);

        if (!(0x5be <= $c && 0x10b7f >= $c)) {
            return static::LTR;
        }

        if (0x85e >= $c) {
            if (0x5be === $c ||
                0x5c0 === $c ||
                0x5c3 === $c ||
                0x5c6 === $c ||
                (0x5d0 <= $c && 0x5ea >= $c) ||
                (0x5f0 <= $c && 0x5f4 >= $c) ||
                0x608 === $c ||
                0x60b === $c ||
                0x60d === $c ||
                0x61b === $c ||
                (0x61e <= $c && 0x64a >= $c) ||
                (0x66d <= $c && 0x66f >= $c) ||
                (0x671 <= $c && 0x6d5 >= $c) ||
                (0x6e5 <= $c && 0x6e6 >= $c) ||
                (0x6ee <= $c && 0x6ef >= $c) ||
                (0x6fa <= $c && 0x70d >= $c) ||
                0x710 === $c ||
                (0x712 <= $c && 0x72f >= $c) ||
                (0x74d <= $c && 0x7a5 >= $c) ||
                0x7b1 === $c ||
                (0x7c0 <= $c && 0x7ea >= $c) ||
                (0x7f4 <= $c && 0x7f5 >= $c) ||
                0x7fa === $c ||
                (0x800 <= $c && 0x815 >= $c) ||
                0x81a === $c ||
                0x824 === $c ||
                0x828 === $c ||
                (0x830 <= $c && 0x83e >= $c) ||
                (0x840 <= $c && 0x858 >= $c) ||
                0x85e === $c) {
                return static::RTL;
            }
        } elseif (0x200f === $c) {
            return static::RTL;
        } elseif (0xfb1d <= $c) {
            if (0xfb1d === $c ||
                (0xfb1f <= $c && 0xfb28 >= $c) ||
                (0xfb2a <= $c && 0xfb36 >= $c) ||
                (0xfb38 <= $c && 0xfb3c >= $c) ||
                0xfb3e === $c ||
                (0xfb40 <= $c && 0xfb41 >= $c) ||
                (0xfb43 <= $c && 0xfb44 >= $c) ||
                (0xfb46 <= $c && 0xfbc1 >= $c) ||
                (0xfbd3 <= $c && 0xfd3d >= $c) ||
                (0xfd50 <= $c && 0xfd8f >= $c) ||
                (0xfd92 <= $c && 0xfdc7 >= $c) ||
                (0xfdf0 <= $c && 0xfdfc >= $c) ||
                (0xfe70 <= $c && 0xfe74 >= $c) ||
                (0xfe76 <= $c && 0xfefc >= $c) ||
                (0x10800 <= $c && 0x10805 >= $c) ||
                0x10808 === $c ||
                (0x1080a <= $c && 0x10835 >= $c) ||
                (0x10837 <= $c && 0x10838 >= $c) ||
                0x1083c === $c ||
                (0x1083f <= $c && 0x10855 >= $c) ||
                (0x10857 <= $c && 0x1085f >= $c) ||
                (0x10900 <= $c && 0x1091b >= $c) ||
                (0x10920 <= $c && 0x10939 >= $c) ||
                0x1093f === $c ||
                0x10a00 === $c ||
                (0x10a10 <= $c && 0x10a13 >= $c) ||
                (0x10a15 <= $c && 0x10a17 >= $c) ||
                (0x10a19 <= $c && 0x10a33 >= $c) ||
                (0x10a40 <= $c && 0x10a47 >= $c) ||
                (0x10a50 <= $c && 0x10a58 >= $c) ||
                (0x10a60 <= $c && 0x10a7f >= $c) ||
                (0x10b00 <= $c && 0x10b35 >= $c) ||
                (0x10b40 <= $c && 0x10b55 >= $c) ||
                (0x10b58 <= $c && 0x10b72 >= $c) ||
                (0x10b78 <= $c && 0x10b7f >= $c)) {
                return static::RTL;
            }
        }

        return static::LTR;
    }

    /**
     * Copy current object string
     *
     * @return Utf8String
     */
    public function copy(): Utf8String
    {
        return clone $this;
    }

    /**
     * Transform the object as a string.
     *
     * @return  string
     */
    public function __toString()
    {
        return $this->_string;
    }
}