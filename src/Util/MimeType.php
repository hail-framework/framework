<?php

namespace Hail\Util;

use Hail\Optimize\OptimizeTrait;

class MimeType
{
    use OptimizeTrait;

    protected static $mimes;
    protected static $extensions;

    protected static function mimes()
    {
        if (static::$mimes === null) {
            static::$mimes = self::optimizeLoad(__DIR__ . '/Data/mimes.php');
        }

        return static::$mimes;
    }

    protected static function extensions()
    {
        if (static::$extensions === null) {
            static::$extensions = self::optimizeLoad(__DIR__ . '/Data/extensions.php');
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
     * @param string $content
     *
     * @return string|null MIME Type or NULL if no mime type detected
     */
    public static function getMimeTypeByContent(string $content)
    {
        if (!\class_exists('\finfo', false)) {
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

    /**
     * Detects MIME Type based on file.
     *
     * @param string $file
     *
     * @return string|null MIME Type or NULL if no mime type detected
     */
    public static function getMimeTypeByFile(string $file)
    {
        if (!\is_file($file) || !\class_exists('\finfo', false)) {
            return null;
        }

        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            return $finfo->file($file) ?: null;
            // @codeCoverageIgnoreStart
        } catch (\ErrorException $e) {
            // This is caused by an array to string conversion error.
            return null;
        }
    } // @codeCoverageIgnoreEnd
}
