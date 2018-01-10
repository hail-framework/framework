<?php

namespace Hail\Image\Imagick\Commands;

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

        return $image->getCore()->modulateImage(100 + $level, 100, 100);
    }
}
