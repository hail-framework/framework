<?php

namespace Hail\Filesystem\Adapter;

class Ftpd extends Ftp
{
    /**
     * @inheritdoc
     */
    public function getMetadata($path)
    {
        if ($path === '') {
            return ['type' => 'dir', 'path' => ''];
        }

        if (! ($object = \ftp_raw($this->getConnection(), 'STAT ' . $path)) || \count($object) < 3) {
            return false;
        }

        if (0 === \strpos($object[1], 'ftpd:')) {
            return false;
        }

        return $this->normalizeObject($object[1], '');
    }

    /**
     * @inheritdoc
     */
    protected function listDirectoryContents($directory, $recursive = true)
    {
        $listing = \ftp_rawlist($this->getConnection(), $directory, $recursive);

        if ($listing === false || ( ! empty($listing) && 0 === \strpos($listing[0], 'ftpd:'))) {
            return [];
        }

        return $this->normalizeListing($listing, $directory);
    }
}
