<?php

namespace Hail\I18n\Gettext\Utils;

use Hail\I18n\Gettext\Translations;

/**
 * Trait to provide the functionality of extracting headers.
 */
trait HeadersGeneratorTrait
{
    /**
     * Returns the headers as a string.
     *
     * @param Translations $translations
     *
     * @return string
     */
    private static function generateHeaders(Translations $translations)
    {
        $headers = '';

        foreach ($translations->getHeaders() as $name => $value) {
            $headers .= "$name: $value\n";
        }

        return $headers;
    }
}
