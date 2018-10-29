<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\{
    Element, Php, TokenInterface
};

abstract class Processor
{
    /**
     * @param string $phpExpression
     *
     * @return Php
     */
    protected static function toPhp(string $phpExpression): Php
    {
        $exp = new Php();
        $exp->setValue($phpExpression);

        return $exp;
    }

    /**
     * insert a php code before an element.
     *
     * @param Element $element
     * @param string  $phpExpression
     */
    protected static function before(Element $element, string $phpExpression): void
    {
        $element->insertBeforeSelf(
            static::toPhp($phpExpression)
        );
    }

    /**
     * insert a php code after an element.
     *
     * @param Element $element
     * @param string  $phpExpression
     */
    protected static function after(Element $element, string $phpExpression): void
    {
        $element->insertAfterSelf(
            static::toPhp($phpExpression)
        );
    }

    /**
     * set inner text of the an element.
     *
     * @param Element $element
     * @param string  $phpExpression
     */
    protected static function text(Element $element, string $phpExpression): void
    {
        $element->removeChildren();

        if ($phpExpression) {
            $element->appendChild(
                static::toPhp($phpExpression)
            );
        }
    }

    /**
     * @param Element $element
     * @param string  $expression
     */
    protected static function addStyle(Element $element, string $expression): void
    {
        $expression = \trim($expression);
        if ($expression) {
            $style = \rtrim($element->getAttribute('style'), ';') . '; ';
            $element->setAttribute('style', $style . $expression);
        }
    }

    /**
     * @param TokenInterface $token
     * @param Processor[]    $processors
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