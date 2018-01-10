<?php

namespace Hail\Image\Gd\Commands;

class ContrastCommand extends \Hail\Image\Commands\AbstractCommand
{
    /**
     * Changes contrast of image
     *
     * @param  \Hail\Image\Image $image
     * @return bool
     */
    public function execute($image)
    {
        $level = $this->argument(0)->between(-100, 100)->required()->value();

        return imagefilter($image->getCore(), IMG_FILTER_CONTRAST, ($level * -1));
    }
}
