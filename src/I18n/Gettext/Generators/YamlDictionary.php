<?php

namespace Hail\I18n\Gettext\Generators;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\DictionaryTrait;

class YamlDictionary extends Generator
{
    use DictionaryTrait;

    public static $options = [
        'includeHeaders' => false,
        'indent' => 2,
        'inline' => 3,
    ];

    /**
     * {@inheritdoc}
     */
    public static function toString(Translations $translations, array $options = [])
    {
        $options += static::$options;

        return \Seralizer::yaml()->encode(
            self::toArray($translations, $options['includeHeaders']),
            $options['inline'],
            $options['indent']
        );
    }
}
