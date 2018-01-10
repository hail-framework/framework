<?php

namespace Hail\I18n\Gettext\Extractors;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\MultidimensionalArrayTrait;
use Hail\Util\Yaml as YamlParser;

/**
 * Class to get gettext strings from yaml.
 */
class Yaml extends Extractor
{
    use MultidimensionalArrayTrait;

    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        $messages = YamlParser::parse($string);

        if (\is_array($messages)) {
            self::fromArray($messages, $translations);
        }
    }
}
