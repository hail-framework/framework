<?php

namespace Hail\I18n\Gettext\Utils;

/*
 * Trait to provide the functionality of read/write csv.
 */
trait CsvTrait
{
    /**
     * @param resource $handle
     * @param array $options
     *
     * @return array
     */
    private static function fgetcsv($handle, $options)
    {
        return \fgetcsv($handle, 0, $options['delimiter'], $options['enclosure'], $options['escape_char']);
    }

    /**
     * @param resource $handle
     * @param array $fields
     * @param array $options
     *
     * @return bool|int
     */
    private static function fputcsv($handle, $fields, $options)
    {
        return \fputcsv($handle, $fields, $options['delimiter'], $options['enclosure'], $options['escape_char']);
    }
}
