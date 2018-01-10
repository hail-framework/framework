<?php

namespace Hail\Image\Gd\Commands;

class BrightnessCommand extends \Hail\Image\Commands\AbstractCommand
{
    /**
     * Changes image brightness
     *
     * @param  \Hail\Image\Image $image
     * @return bool
     */
    public function execute($image)
    {
        $level = $this->argument(0)->between(-100, 100)->required()->value();

        return imagefilter($image->getCore(), IMG_FILTER_BRIGHTNESS, ($level * 2.55));
    }
}
