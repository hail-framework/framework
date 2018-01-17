<?php

namespace Hail\Template\Tokenizer\Token;

final class PhpEcho extends Php
{
    public function parse(string $html): string
    {
        $return = $this->parseHtml($html, 3, '?>');

        if ($this->value !== null) {
            $this->value = 'echo ' . $this->value;
        }

        return $return ?? '';
    }
}
