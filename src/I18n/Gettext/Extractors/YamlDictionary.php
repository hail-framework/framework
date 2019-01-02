<?php

namespace Hail\I18n\Gettext\Extractors;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\DictionaryTrait;

/**
 * Class to get gettext strings from yaml.
 */
class YamlDictionary extends Extractor
{
    use DictionaryTrait;

    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        $messages = \Seralizer::yaml()->decode($string);

        if (\is_array($messages)) {
            self::fromArray($messages, $translations);
        }
    }
}
