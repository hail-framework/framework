<?php

namespace Hail\Image\Gd\Commands;

class OpacityCommand extends \Hail\Image\Commands\AbstractCommand
{
    /**
     * Defines opacity of an image
     *
     * @param  \Hail\Image\Image $image
     * @return bool
     */
    public function execute($image)
    {
        $transparency = $this->argument(0)->between(0, 100)->required()->value();

        // get size of image
        $size = $image->getSize();

        // build temp alpha mask
        $mask_color = sprintf('rgba(0, 0, 0, %.1F)', $transparency / 100);
        $mask = $image->getDriver()->newImage($size->width, $size->height, $mask_color);

        // mask image
        $image->mask($mask->getCore(), true);

        return true;
    }
}
