<?php

namespace Hail\Console\Util;

trait StringLengthTrait
{
    /**
     * Tags the should not be ultimately considered
     * when calculating any string lengths
     *
     * @var array $ignoreTags
     */
    protected $ignoreTags = [];

    /**
     * Set the ignore tags property
     */
    protected function setIgnoreTags()
    {
        if ($this->ignoreTags === []) {
            $this->ignoreTags = array_keys($this->parser->tags->all());
        }
    }

    /**
     * Determine the length of the string without any tags
     *
     * @param  string  $str
     *
     * @return integer
     */
    protected function lengthWithoutTags($str)
    {
        $this->setIgnoreTags();

        return mb_strwidth($this->withoutTags($str));
    }

    /**
     * Get the string without the tags that are to be ignored
     *
     * @param  string $str
     *
     * @return string
     */
    protected function withoutTags($str)
    {
        $this->setIgnoreTags();

        return str_replace($this->ignoreTags, '', $str);
    }

    /**
     * Apply padding to a string
     *
     * @param string $str
     * @param string $finalLength
     * @param string $paddingSide
     *
     * @return string
     */
    protected function pad($str, $finalLength, $paddingSide = 'right')
    {
        $padding = $finalLength - $this->lengthWithoutTags($str);

        if ($paddingSide === 'left') {
            return str_repeat(' ', $padding) . $str;
        }

        return $str . str_repeat(' ', $padding);
    }

    /**
     * Apply padding to an array of strings
     *
     * @param  array $arr
     * @param  integer $finalLength
     * @param string $paddingSide
     *
     * @return array
     */
    protected function padArray($arr, $finalLength, $paddingSide = 'right')
    {
        foreach ($arr as $key => $value) {
            $arr[$key] = $this->pad($value, $finalLength, $paddingSide);
        }

        return $arr;
    }

    /**
     * Find the max string length in an array
     *
     * @param array $arr
     * @return int
     */
    protected function maxStrLen(array $arr)
    {
        return max($this->arrayOfStrLens($arr));
    }

    /**
     * Get an array of the string lengths from an array of strings
     *
     * @param array $arr
     * @return array
     */
    protected function arrayOfStrLens(array $arr)
    {
        return array_map([$this, 'lengthWithoutTags'], $arr);
    }
}
