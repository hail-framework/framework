<?php

namespace Hail\I18n\Gettext\Generators;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\MultidimensionalArrayTrait;
use Hail\Util\Yaml as YamlDumper;

class Yaml extends Generator
{
    use MultidimensionalArrayTrait;

    public static $options = [
        'includeHeaders' => false,
        'indent' => 2,
        'inline' => 4,
    ];

    /**
     * {@inheritdoc}
     */
    public static function toString(Translations $translations, array $options = [])
    {
        $options += static::$options;

        return YamlDumper::dump(
            self::toArray($translations, $options['includeHeaders']),
            $options['inline'],
            $options['indent']
        );
    }
}
