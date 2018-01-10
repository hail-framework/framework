<?php

namespace Hail\Util;


class MimeType
{
    use OptimizeTrait;

    protected static $mimes;
    protected static $extensions;

    protected static function mimes()
    {
        if (static::$mimes === null) {
            $file = __DIR__ . '/Data/mimes.php';

            $mimes = self::optimizeGet('mimes', $file);
            if ($mimes === false) {
                $mimes = require $file;

                self::optimizeSet('mimes', $mimes, $file);
            }

            static::$mimes = $mimes;
        }

        return static::$mimes;
    }

    protected static function extensions()
    {
        if (static::$extensions === null) {
            $file = __DIR__ . '/Data/extensions.php';

            $extensions = self::optimizeGet('extensions', $file);
            if ($extensions === false) {
                $extensions = require $file;

                self::optimizeSet('extensions', $extensions, $file);
            }

            static::$extensions = $extensions;
        }

        return static::$extensions;
    }

    public static function getMimeType($extension)
    {
        return self::getMimeTypes($extension)[0] ?? null;
    }

    public static function getExtension($mimeType)
    {
        return static::getExtensions($mimeType)[0] ?? null;
    }

    public static function getMimeTypes($extension)
    {
        $mimes = static::$mimes ?? static::mimes();
        $extension = \strtolower(\trim($extension));

        if (($pos = \strrpos($extension, '.')) !== false) {
            $extension = \substr($extension, $pos + 1);
        }

        return $mimes[$extension] ?? [];
    }

    public static function getExtensions($mimeType)
    {
        $extensions = static::$extensions ?? static::extensions();
        $mimeType = \strtolower(\trim($mimeType));

        return $extensions[$mimeType] ?? null;
    }

    /**
     * Detects MIME Type based on given content.
     *
     * @param mixed $content
     *
     * @return string|null MIME Type or NULL if no mime type detected
     */
    public static function getMimeTypeByContent($content)
    {
        if (!\is_string($content) || !\class_exists('\finfo', false)) {
            return null;
        }

        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            return $finfo->buffer($content) ?: null;
            // @codeCoverageIgnoreStart
        } catch (\ErrorException $e) {
            // This is caused by an array to string conversion error.
            return null;
        }
    } // @codeCoverageIgnoreEnd
}