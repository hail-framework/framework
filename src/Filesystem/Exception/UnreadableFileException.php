<?php
namespace Hail\Filesystem\Exception;

class UnreadableFileException extends FileSystemException
{
    public static function forFileInfo(\SplFileInfo $fileInfo)
    {
        return new static('Unreadable file encountered: ' . $fileInfo->getRealPath());
    }
}
