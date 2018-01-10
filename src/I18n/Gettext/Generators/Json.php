<?php

namespace Hail\I18n\Gettext\Generators;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\MultidimensionalArrayTrait;

class Json extends Generator
{
    use MultidimensionalArrayTrait;

    public static $options = [
        'json' => 0,
        'includeHeaders' => false,
    ];

    /**
     * {@inheritdoc}
     */
    public static function toString(Translations $translations, array $options = [])
    {
        $options += static::$options;

        return \json_encode(self::toArray($translations, $options['includeHeaders'], true), $options['json']);
    }
}
