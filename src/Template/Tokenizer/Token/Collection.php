<?php

namespace Hail\Template\Tokenizer\Token;


use Hail\Template\Tokenizer\Tokenizer;

final class Collection extends AbstractToken implements \IteratorAggregate
{
    public function __construct()
    {
        parent::__construct(TokenInterface::ROOT);
    }

    public function parse(string $html): string
    {
        $remainingHtml = \trim($html);
        while ($remainingHtml !== '') {
            $token = Tokenizer::buildFromHtml($remainingHtml);

            if (!$token instanceof TokenInterface) {
                // Error has occurred, so we stop.
                break;
            }

            $remainingHtml = $token->parse($remainingHtml);
            $this->children[] = $token;
        }

        return $remainingHtml;
    }

    public function toArray(): array
    {
        $result = [];
        foreach ($this->children as $token) {
            $result[] = $token->toArray();
        }

        return $result;
    }

    public function __toString(): string
    {
        $result = '';
        foreach ($this->children as $token) {
            $result .= (string) $token;
        }

        return $result;
    }

    /**
     * Required by the IteratorAggregate interface.
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->children);
    }
}