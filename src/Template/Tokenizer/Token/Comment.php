<?php

namespace Hail\Template\Tokenizer\Token;

use Hail\Template\Tokenizer\Exception\ParseException;

final class Comment extends AbstractToken
{
    public function __construct(TokenInterface $parent = null)
    {
        parent::__construct(TokenInterface::COMMENT, $parent);
    }

    public function parse(string $html): string
    {
        $return = $this->parseHtml($html, 4, '-->');

        if ($return === null && $this->getThrowOnError()) {
            throw new ParseException('Invalid comment.');
        }

        return $return ?? '';
    }

    public function __toString(): string
    {
        return "<!--{$this->value}-->";
    }
}
