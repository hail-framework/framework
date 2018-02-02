<?php

namespace Hail\Template\Processor\Vue;

use Hail\Template\Processor\Vue\Parser\FilterParser;
use Hail\Template\Tokenizer\Token\Element;
use Hail\Template\Processor\Helpers;
use Hail\Template\Processor\ProcessorInterface;
use Hail\Template\Tokenizer\Token\Text;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class VuePhp implements ProcessorInterface
{
    /**
     * @var ProcessorInterface[]
     */
    private const PROCESSORS = [
        VueFor::class => Element::class,
        VueShow::class => Element::class,
        VueIf::class => Element::class,
        VueText::class => Element::class,
        VueHtml::class => Element::class,
        VueBind::class => Element::class,
        TextExpression::class => Text::class,
    ];

    /**
     * @var FilterParser
     */
    public static $parser;


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

        self::$parser = new FilterParser();

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