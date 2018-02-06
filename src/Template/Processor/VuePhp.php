<?php

namespace Hail\Template\Processor;

use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Tokenizer\Token\TokenInterface;
use Hail\Template\Processor\Vue\{
    VueFor, VueShow, VueIf, VueText, VueHtml, VueBind,
    TextExpression
};

final class VuePhp extends Processor
{
    /**
     * @var Processor[]
     */
    private const PROCESSORS = [
        VueFor::class,
        VueShow::class,
        VueIf::class,
        VueText::class,
        VueHtml::class,
        VueBind::class,
        TextExpression::class,
    ];

    public static function process(TokenInterface $element): bool
    {
        if (!$element instanceof Element) {
            return false;
        }

        if (!$element->hasAttribute('v-php')) {
            return $element->getName() === 'template';
        }

        $once = $element->hasAttribute('v-once');
        if (!$once) {
            if ($element->hasAttribute('v-if')) {
                $elements = self::findVueIfBlocks($element);
                $end = \end($elements);

                foreach ($elements as $v) {
                    self::after($end, clone $v);
                }
            } elseif (
                !$element->hasAttribute('v-else-if') &&
                !$element->hasAttribute('v-else')
            ) {
                self::insertAfter($element, clone $element);
            }
        }

        self::parseToken($element, self::PROCESSORS);

        if ($element->getName() === 'template') {
            foreach ($element->getChildren() as $child) {
                $element->insertBeforeSelf($child);
            }
            $element->remove();
        } elseif ($once) {
            $element->removeAttribute('v-once');
        } else {
            $element->setAttribute('v-if', 'false');
        }

        return true;
    }

    private static function insertAfter(Element $ref, Element $new)
    {
        $new->removeAttribute('v-php');

        foreach ($new->getChildren() as $child) {
            if ($child instanceof Element) {
                $child->removeAttribute('v-php');
            }
        }

        $ref->insertAfterSelf($new);
    }
}