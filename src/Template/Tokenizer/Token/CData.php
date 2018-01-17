<?php

namespace Hail\Template\Tokenizer\Token;

use Hail\Template\Tokenizer\Exception\ParseException;

final class CData extends AbstractToken
{
    public function __construct(TokenInterface $parent = null)
    {
        parent::__construct(TokenInterface::CDATA, $parent);
    }

    public function parse(string $html): string
    {
        $return = $this->parseHtml($html, 9, ']]>');

        if ($return === null && $this->getThrowOnError()) {
            throw new ParseException('Invalid CDATA.');
        }

        return $return ?? '';
    }

    public function __toString(): string
    {
        return "<![CDATA[{$this->value}]]>";
    }
}
