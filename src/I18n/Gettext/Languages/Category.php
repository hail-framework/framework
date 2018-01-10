<?php

namespace Hail\I18n\Gettext\Languages;

/**
 * A helper class that handles a single category rules (eg 'zero', 'one', ...) and its formula and examples.
 */
class Category
{
    /**
     * The category identifier (eg 'zero', 'one', ..., 'other').
     *
     * @var string
     */
    public $id;
    /**
     * The gettext formula that identifies this category (null if and only if the category is 'other').
     *
     * @var string|null
     */
    public $formula;
    /**
     * The CLDR representation of some exemplar numeric ranges that satisfy this category.
     *
     * @var string|null
     */
    public $examples;

    /**
     * Initialize the instance and parse the formula.
     *
     * @param string $cldrCategoryId         The CLDR category identifier (eg 'pluralRule-count-one').
     * @param string $cldrFormulaAndExamples The CLDR formula and examples (eg 'i = 1 and v = 0 @integer 1').
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function __construct($cldrCategoryId, $cldrFormulaAndExamples)
    {
        $matches = [];
        if (!\preg_match('/^pluralRule-count-(.+)$/', $cldrCategoryId, $matches)) {
            throw new \InvalidArgumentException("Invalid CLDR category: '$cldrCategoryId'");
        }
        if (!\in_array($matches[1], CldrData::$categories, true)) {
            throw new \InvalidArgumentException("Invalid CLDR category: '$cldrCategoryId'");
        }
        $this->id = $matches[1];
        $cldrFormulaAndExamplesNormalized = \trim(\preg_replace('/\s+/', ' ', $cldrFormulaAndExamples));
        if (!\preg_match('/^([^@]*)(?:@integer([^@]+))?(?:@decimal(?:[^@]+))?$/', $cldrFormulaAndExamplesNormalized,
            $matches)) {
            throw new \InvalidArgumentException("Invalid CLDR category rule: $cldrFormulaAndExamples");
        }
        $cldrFormula = \trim($matches[1]);
        $s = isset($matches[2]) ? \trim($matches[2]) : '';
        $this->examples = ($s === '') ? null : $s;

        if ($this->id === CldrData::OTHER_CATEGORY) {
            if ($cldrFormula !== '') {
                throw new \RuntimeException("The '" . CldrData::OTHER_CATEGORY . "' category should not have any formula, but it has '$cldrFormula'");
            }
            $this->formula = null;
        } else {
            if ($cldrFormula === '') {
                throw new \RuntimeException("The '{$this->id}' category does not have a formula");
            }
            $this->formula = FormulaConverter::convertFormula($cldrFormula);
        }
    }

    /**
     * Return a list of numbers corresponding to the $examples value.
     *
     * @throws \InvalidArgumentException Throws an Exception if we weren't able to expand the examples.
     * @return int[]
     */
    public function getExampleIntegers()
    {
        return self::expandExamples($this->examples);
    }

    /**
     * Expand a list of examples as defined by CLDR.
     *
     * @param string $examples A string like '1, 2, 5...7, …'.
     *
     * @throws \InvalidArgumentException Throws an Exception if we weren't able to expand $examples.
     * @return int[]
     */
    public static function expandExamples($examples)
    {
        $result = [];
        $m = null;

        $len = \strlen(', …');
        if (\substr($examples, -$len) === ', …') {
            $examples = \substr($examples, 0, \strlen($examples) - $len);
        }

        foreach (\explode(',', \str_replace(' ', '', $examples)) as $range) {
            if (\preg_match('/^\d+$/', $range)) {
                $result[] = (int) $range;
            } elseif (\preg_match('/^(\d+)~(\d+)$/', $range, $m)) {
                $from = (int) $m[1];
                $to = (int) $m[2];
                $delta = $to - $from;
                $step = (int) \max(1, $delta / 100);
                for ($i = $from; $i < $to; $i += $step) {
                    $result[] = $i;
                }
                $result[] = $to;
            } else {
                throw new \InvalidArgumentException("Unhandled test range '$range' in '$examples'");
            }
        }

        if (empty($result)) {
            throw new \InvalidArgumentException("No test numbers from '$examples'");
        }

        return $result;
    }
}
