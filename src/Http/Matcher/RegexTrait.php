<?php

namespace Hail\Http\Matcher;


trait RegexTrait
{
    /**
     * @var array
     */
    protected $parts = [];

    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var bool
     */
    protected $reverse = false;

    /**
     * @param string $value
     * @param string $tag
     * @param bool   $reverse
     */
    protected function split(string $value, string $tag, bool $reverse = false): void
    {
        $this->delimiter = $tag;

        $temp = '';
        $parts = \explode($tag, $value);
        foreach ($parts as $part) {
            $started = $temp !== '';

            if ($part[\strlen($part) - 1] === '}') {
                if ($started) {
                    $part = $temp . $tag . $part;
                    $temp = '';
                }
            } elseif ($started) {
                $temp .= $tag . $part;
                continue;
            } elseif ($part[0] === '{') {
                $temp = $part;
                continue;
            }

            $this->parts[] = $part;
        }

        if ($reverse) {
            $this->parts = \array_reverse($this->parts);
            $this->reverse = true;
        }
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function regex(string $value): bool
    {
        if (!$this->delimiter) {
            return false;
        }

        $parts = \explode($this->delimiter, $value);
        if ($this->reverse) {
            $parts = \array_reverse($parts);
        }

        foreach ($parts as $k => $part) {
            $check = $this->parts[$k];

            if ($check === '') {
                continue;
            }

            if ($check[0] === '{') {
                if (!\preg_match('/^' . \preg_quote(\substr($check, 1, -1), '/') . '$/i', $part)) {
                    return false;
                }
            } elseif ($part !== $check) {
                return false;
            }
        }

        return true;
    }
}