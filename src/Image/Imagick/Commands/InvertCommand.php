<?php

namespace Hail\Image\Imagick\Commands;

class InvertCommand extends \Hail\Image\Commands\AbstractCommand
{
    /**
     * Inverts colors of an image
     *
     * @param  \Hail\Image\Image $image
     * @return bool
     */
    public function execute($image)
    {
        return $image->getCore()->negateImage(false);
    }
}
