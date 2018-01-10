<?php

namespace Hail\Image\Gd\Commands;

class BackupCommand extends \Hail\Image\Commands\AbstractCommand
{
    /**
     * Saves a backups of current state of image core
     *
     * @param  \Hail\Image\Image $image
     * @return bool
     */
    public function execute($image)
    {
        $backupName = $this->argument(0)->value();

        // clone current image resource
        $clone = clone $image;
        $image->setBackup($clone->getCore(), $backupName);

        return true;
    }
}
