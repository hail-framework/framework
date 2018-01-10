<?php

namespace Hail\Template\Processor;

use Hail\Template\Html\Token\Element;
use Hail\Template\Html\Token\Php;
use Hail\Template\Html\Token\TokenInterface;

class Helpers
{
    /**
     * insert a php code before an element.
     *
     * @param Element     $element
     * @param             $phpExpression
     */
    public static function before(Element $element, $phpExpression): void
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
    public static function after(Element $element, $phpExpression): void
    {
        $exp = new Php();
        $exp->setValue($phpExpression);

        $element->insertAfterSelf($exp);
    }

    /**
     * set inner text of the an element.
     *
     * @param Element $element
     * @param             $phpExpression
     */
    public static function text(Element $element, $phpExpression): void
    {
        $element->removeChildren();

        if ($phpExpression) {
            $exp = new Php();
            $exp->setValue($phpExpression);

            $element->appendChild($exp);
        }
    }

    public static function addStyle(Element $element, string $expression): void
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
    public static function findVueIfBlocks(Element $element): array
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
     * @param TokenInterface $element
     * @param array          $processors
     */
    public static function parseElement(TokenInterface $element, array $processors): void
    {
        if ($element instanceof Element) {
            foreach ($processors as $processor) {
                if ($processor::process($element)) {
                    return;
                }
            }
        }

        foreach ($element->getChildren() as $childNode) {
            self::parseElement($childNode, $processors);
        }
    }

}