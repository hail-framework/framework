<?php

namespace Hail\I18n\Gettext\Extractors;

use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\HeadersExtractorTrait;
use Hail\I18n\Gettext\Utils\CsvTrait;

/**
 * Class to get gettext strings from csv.
 */
class Csv extends Extractor
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
            $context = \array_shift($row);
            $original = \array_shift($row);

            if ($context === '' && $original === '') {
                self::extractHeaders(\array_shift($row), $translations);
                continue;
            }

            $translation = $translations->insert($context, $original);

            if (!empty($row)) {
                $translation->setTranslation(\array_shift($row));
                $translation->setPluralTranslations($row);
            }
        }

        \fclose($handle);
    }
}
