<?php

namespace Hail\I18n\Gettext\Languages;

/**
 * A helper class to convert a CLDR formula to a gettext formula.
 */
class FormulaConverter
{
    /**
     * Converts a formula from the CLDR representation to the gettext representation.
     *
     * @param string $cldrFormula The CLDR formula to convert.
     *
     * @throws \InvalidArgumentException
     * @return bool|string Returns true if the gettext will always evaluate to true, false if gettext will always evaluate to false, return the gettext formula otherwise.
     */
    public static function convertFormula($cldrFormula)
    {
        if (\strpbrk($cldrFormula, '()') !== false) {
            throw new \InvalidArgumentException("Unable to convert the formula '$cldrFormula': parenthesis handling not implemented");
        }
        $orSeparatedChunks = [];
        foreach (\explode(' or ', $cldrFormula) as $cldrFormulaChunk) {
            $gettextFormulaChunk = null;
            $andSeparatedChunks = [];
            foreach (\explode(' and ', $cldrFormulaChunk) as $cldrAtom) {
                $gettextAtom = self::convertAtom($cldrAtom);
                if ($gettextAtom === false) {
                    // One atom joined by 'and' always evaluates to false => the whole 'and' group is always false
                    $gettextFormulaChunk = false;
                    break;
                }

                if ($gettextAtom !== true) {
                    $andSeparatedChunks[] = $gettextAtom;
                }
            }

            if (!isset($gettextFormulaChunk)) {
                if (empty($andSeparatedChunks)) {
                    // All the atoms joined by 'and' always evaluate to true => the whole 'and' group is always true
                    $gettextFormulaChunk = true;
                } else {
                    $gettextFormulaChunk = \implode(' && ', $andSeparatedChunks);
                    // Special cases simplification
                    switch ($gettextFormulaChunk) {
                        case 'n >= 0 && n <= 2 && n != 2':
                            $gettextFormulaChunk = 'n == 0 || n == 1';
                            break;
                    }
                }
            }
            if ($gettextFormulaChunk === true) {
                // One part of the formula joined with the others by 'or' always evaluates to true => the whole formula always evaluates to true
                return true;
            }

            if ($gettextFormulaChunk !== false) {
                $orSeparatedChunks[] = $gettextFormulaChunk;
            }
        }
        if (empty($orSeparatedChunks)) {
            // All the parts joined by 'or' always evaluate to false => the whole formula always evaluates to false
            return false;
        }

        return \implode(' || ', $orSeparatedChunks);
    }

    /**
     * Converts an atomic part of the CLDR formula to its gettext representation.
     *
     * @param string $cldrAtom The CLDR formula atom to convert.
     *
     * @throws \InvalidArgumentException
     * @return bool|string Returns true if the gettext will always evaluate to true, false if gettext will always evaluate to false, return the gettext formula otherwise.
     */
    private static function convertAtom($cldrAtom)
    {
        $m = null;
        $gettextAtom = $cldrAtom;
        $gettextAtom = \str_replace([' = ', 'i'], [' == ', 'n'], $gettextAtom);

        if (\preg_match('/^n( % \d+)? (!=|==) \d+$/', $gettextAtom)) {
            return $gettextAtom;
        }
        if (\preg_match('/^n( % \d+)? (!=|==) \d+(,\d+|\.\.\d+)+$/', $gettextAtom)) {
            return self::expandAtom($gettextAtom);
        }
        if (\preg_match('/^(?:v|w)(?: % 10+)? == (\d+)(?:\.\.\d+)?$/', $gettextAtom,
            $m)) { // For gettext: v == 0, w == 0
            return ((int) $m[1]) === 0;
        }
        if (\preg_match('/^(?:v|w)(?: % 10+)? != (\d+)(?:\.\.\d+)?$/', $gettextAtom,
            $m)) { // For gettext: v == 0, w == 0
            return ((int) $m[1]) !== 0;
        }
        if (\preg_match('/^(?:f|t)(?: % 10+)? == (\d+)(?:\.\.\d+)?$/', $gettextAtom, $m)) { // f == empty, t == empty
            return ((int) $m[1]) === 0;
        }
        if (\preg_match('/^(?:f|t)(?: % 10+)? != (\d+)(?:\.\.\d+)?$/', $gettextAtom, $m)) { // f == empty, t == empty
            return ((int) $m[1]) !== 0;
        }

        throw new \InvalidArgumentException("Unable to convert the formula chunk '$cldrAtom' from CLDR to gettext");
    }

    /**
     * Expands an atom containing a range (for instance: 'n == 1,3..5').
     *
     * @param string $atom
     *
     * @throws \InvalidArgumentException
     * @return string
     */
    private static function expandAtom($atom)
    {
        $m = null;
        if (\preg_match('/^(n(?: % \d+)?) (==|!=) (\d+(?:\.\.\d+|,\d+)+)$/', $atom, $m)) {
            $what = $m[1];
            $op = $m[2];
            $chunks = [];

            $equal = $op === '==';
            $notEqual = $op === '!=';

            foreach (\explode(',', $m[3]) as $range) {
                $chunk = null;
                if (null === $chunk) {
                    if (\preg_match('/^\d+$/', $range)) {
                        $chunk = "$what $op $range";
                    } elseif (\preg_match('/^(\d+)\.\.(\d+)$/', $range, $m)) {
                        $from = (int) $m[1];
                        $to = (int) $m[2];
                        if (($to - $from) === 1) {
                            if ($equal) {
                                $chunk = "($what == $from || $what == $to)";
                            } elseif ($notEqual) {
                                $chunk = "$what != $from && $what == $to";
                            }
                        } elseif ($equal) {
                            $chunk = "$what >= $from && $what <= $to";
                        } elseif ($notEqual) {
                            $chunk = "($what < $from || $what > $to)";
                            if ($what === 'n' && $from <= 0) {
                                $chunk = "$what > $to";
                            }
                        }
                    }
                }

                if (null === $chunk) {
                    throw new \InvalidArgumentException("Unhandled range '$range' in '$atom'");
                }

                $chunks[] = $chunk;
            }

            if (\count($chunks) === 1) {
                return $chunks[0];
            }

            if ($equal) {
                return '(' . \implode(' || ', $chunks) . ')';
            }

            if ($notEqual) {
                return \implode(' && ', $chunks);
            }
        }

        throw new \InvalidArgumentException("Unable to expand '$atom'");
    }
}
