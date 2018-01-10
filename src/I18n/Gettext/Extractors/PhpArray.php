<?php

namespace Hail\I18n\Gettext\Extractors;

use BadMethodCallException;
use Hail\I18n\Gettext\Translations;
use Hail\I18n\Gettext\Utils\MultidimensionalArrayTrait;

/**
 * Class to get gettext strings from php files returning arrays.
 */
class PhpArray extends Extractor
{
    use MultidimensionalArrayTrait;

    /**
     * {@inheritdoc}
     */
    public static function fromFile($file, Translations $translations, array $options = [])
    {
        foreach (static::getFiles($file) as $f) {
            self::fromArray(include($f), $translations);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function fromString($string, Translations $translations, array $options = [])
    {
        throw new BadMethodCallException('PhpArray::fromString() cannot be called. Use PhpArray::fromFile()');
    }
}
