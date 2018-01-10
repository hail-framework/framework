<?php

namespace Hail\Http\Matcher;


trait NegativeResultTrait
{
    protected $result = true;

    protected function getValue(string $value): string
    {
        if ($value[0] === '!') {
            $this->result = false;

            return \substr($value, 1);
        }

        return $value;
    }
}