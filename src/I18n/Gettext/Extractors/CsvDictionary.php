<?php

namespace Hail\I18n\Gettext\Extractors;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\HeadersExtractorTrait;
use Hail\I18n\Gettext\Utils\CsvTrait;

/**
 * Class to get gettext strings from csv.
 */
class CsvDictionary extends Extractor
{
    use HeadersExtractorTrait;
    use CsvTrait;

    public static $options = [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape_char' => '\\'
    ];

    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        $options += static::$options;
        $handle = \fopen('php://memory', 'wb');

        \fwrite($handle, $string);
        \rewind($handle);

        while ($row = self::fgetcsv($handle, $options)) {
            list($original, $translation) = $row + ['', ''];

            if ($original === '') {
                self::extractHeaders($translation, $translations);
                continue;
            }

            $translations->insert(null, $original)->setTranslation($translation);
        }

        \fclose($handle);
    }
}
