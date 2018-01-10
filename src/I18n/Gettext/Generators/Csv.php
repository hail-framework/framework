<?php

namespace Hail\I18n\Gettext\Generators;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\HeadersGeneratorTrait;
use Hail\I18n\Gettext\Utils\CsvTrait;

/**
 * Class to export translations to csv.
 */
class Csv extends Generator
{
    use HeadersGeneratorTrait;
    use CsvTrait;

    public static $options = [
        'includeHeaders' => false,
        'delimiter' => ",",
        'enclosure' => '"',
        'escape_char' => "\\"
    ];

    /**
     * {@parentDoc}.
     */
    public static function toString(Translations $translations, array $options = [])
    {
        $options += static::$options;
        $handle = \fopen('php://memory', 'wb');

        if ($options['includeHeaders']) {
            self::fputcsv($handle, ['', '', self::generateHeaders($translations)], $options);
        }

        foreach ($translations as $translation) {
            $line = [$translation->getContext(), $translation->getOriginal(), $translation->getTranslation()];

            if ($translation->hasPluralTranslations(true)) {
                $line = \array_merge($line, $translation->getPluralTranslations());
            }

            self::fputcsv($handle, $line, $options);
        }

        \rewind($handle);
        $csv = \stream_get_contents($handle);
        \fclose($handle);

        return $csv;
    }
}
