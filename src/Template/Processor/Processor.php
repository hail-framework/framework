<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    Element, Php, TokenInterface
};

abstract class Processor
{
    /**
     * insert a php code before an element.
     *
     * @param Element     $element
     * @param             $phpExpression
     */
    protected static function before(Element $element, $phpExpression): void
    {
        $exp = new Php();
        $exp->setValue($phpExpression);

        $element->insertBeforeSelf($exp);
    }

    /**
     * insert a php code after an element.
     *
     * @param Element     $element
     * @param             $phpExpression
     */
    protected static function after(Element $element, $phpExpression): void
    {
        $exp = new Php();
        $exp->setValue($phpExpression);

        $element->insertAfterSelf($exp);
    }

    /**
     * set inner text of the an element.
     *
     * @param Element     $element
     * @param             $phpExpression
     */
    protected static function text(Element $element, $phpExpression): void
    {
        $element->removeChildren();

        if ($phpExpression) {
            $exp = new Php();
            $exp->setValue($phpExpression);

            $element->appendChild($exp);
        }
    }

    protected static function addStyle(Element $element, string $expression): void
    {
        $expression = \trim($expression);
        if ($expression) {
            $style = \rtrim($element->getAttribute('style'), ';') . '; ';
            $element->setAttribute('style', $style . $expression);
        }
    }

    /**
     * @param Element $element
     *
     * @return Element[]
     */
    protected static function findVueIfBlocks(Element $element): array
    {
        $next = $element->getNextSibling();

        if (
            $next !== null && (
                $next->hasAttribute('v-else') ||
                $next->hasAttribute('v-else-if')
            )
        ) {
            $elements = self::findVueIfBlocks($next);
            \array_unshift($elements, $element);

            return $elements;
        }

        return [$element];
    }

    /**
     * @param TokenInterface       $token
     * @param Processor[] $processors
     */
    public static function parseToken(
        TokenInterface $token,
        array $processors
    ): void {
        foreach ($processors as $processor) {
            if ($processor::process($token)) {
                return;
            }
        }

        if ($token instanceof Element) {
            foreach ($token->getChildren() as $childNode) {
                self::parseToken($childNode, $processors);
            }
        }
    }

    abstract public static function process(TokenInterface $element): bool;
}