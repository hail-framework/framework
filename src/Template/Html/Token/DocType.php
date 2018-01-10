<?php

namespace Hail\Template\Html\Token;

use Hail\Template\Html\Exception\ParseException;

final class DocType extends AbstractToken
{
    public function __construct(TokenInterface $parent = null)
    {
        parent::__construct(TokenInterface::DOCTYPE, $parent);
    }

    public function parse(string $html): string
    {
        $return = $this->parseHtml($html, 10, '>');

        if ($return === null && $this->getThrowOnError()) {
            throw new ParseException('Invalid DOCTYPE.');
        }

        return $return ?? '';
    }

    public function __toString(): string
    {
        return "<!DOCTYPE {$this->value}>";
    }
}
