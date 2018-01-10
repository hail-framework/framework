<?php

namespace Hail\Image\Gd\Commands;

class BlurCommand extends \Hail\Image\Commands\AbstractCommand
{
    /**
     * Applies blur effect on image
     *
     * @param  \Hail\Image\Image $image
     * @return bool
     */
    public function execute($image)
    {
        $amount = (int) $this->argument(0)->between(0, 100)->value(1);

        for ($i=0; $i < $amount; ++$i) {
            imagefilter($image->getCore(), IMG_FILTER_GAUSSIAN_BLUR);
        }

        return true;
    }
}
