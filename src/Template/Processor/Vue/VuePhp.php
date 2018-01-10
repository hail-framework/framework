<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Html\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;

final class VuePhp implements ProcessorInterface
{
    /**
     * @var ProcessorInterface[]
     */
    private const PROCESSORS = [
        VueFor::class,
        VueShow::class,
        VueIf::class,
        VueText::class,
        VueHtml::class,
        VueBind::class,
    ];

    public static function process(Element $element): bool
    {
        if (!$element->hasAttribute('v-php')) {
            return $element->getName() === 'template';
        }

        $once = $element->hasAttribute('v-once');
        if (!$once) {
            if ($element->hasAttribute('v-if')) {
                $elements = Helpers::findVueIfBlocks($element);
                $end = \end($elements);

                foreach ($elements as $v) {
                    self::after($end, clone $v);
                }
            } elseif (
                !$element->hasAttribute('v-else-if') &&
                !$element->hasAttribute('v-else')
            ) {
                self::after($element, clone $element);
            }
        }

        Helpers::parseElement($element, self::PROCESSORS);

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

    private static function after(Element $ref, Element $new)
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