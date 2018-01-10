<?php

namespace Hail\I18n\Gettext\Extractors;

use Hail\I18n\Gettext\Translations;

abstract class Extractor implements ExtractorInterface
{
    /**
     * {@inheritdoc}
     */
    public static function fromFile($file, Translations $translations, array $options = [])
    {
        foreach (self::getFiles($file) as $f) {
            $options['file'] = $f;
            static::fromString(\file_get_contents($f), $translations, $options);
        }
    }

    /**
     * Checks and returns all files.
     *
     * @param string|array $file The file/s
     *
     * @return array The file paths
     */
    protected static function getFiles($file): array
    {
        if (empty($file)) {
            throw new \InvalidArgumentException('There is not any file defined');
        }

        if (\is_string($file)) {
            if (!\is_file($file)) {
                throw new \InvalidArgumentException("'$file' is not a valid file");
            }

            if (!\is_readable($file)) {
                throw new \InvalidArgumentException("'$file' is not a readable file");
            }

            return [$file];
        }

        if (\is_array($file)) {
            $files = [];

            foreach ($file as $f) {
                $files = self::getFiles($f);
            }

            return \array_merge(...$files);
        }

        throw new \InvalidArgumentException('The first argument must be string or array');
    }
}
