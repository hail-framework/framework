<?php

namespace Hail\I18n\Gettext\Generators;

use Hail\I18n\Gettext\Translations;

abstract class Generator implements GeneratorInterface
{
    /**
     * {@inheritdoc}
     */
    public static function toFile(Translations $translations, $file, array $options = [])
    {
        $content = static::toString($translations, $options);
        $length = \strlen($content);

        return \file_put_contents($file, $content) === $length;
    }
}
