<?php

namespace Hail\Image\Gd\Commands;

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
        return imagefilter($image->getCore(), IMG_FILTER_NEGATE);
    }
}
