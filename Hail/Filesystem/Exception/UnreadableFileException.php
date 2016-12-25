<?php
namespace Hail\Filesystem\Exception;

class UnreadableFileException extends FileSystemException
{
    public static function forFileInfo(\SplFileInfo $fileInfo)
    {
        return new static(
            sprintf(
                'Unreadable file encountered: %s',
                $fileInfo->getRealPath()
            )
        );
    }
}
