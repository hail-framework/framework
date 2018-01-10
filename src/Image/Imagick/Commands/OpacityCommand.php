<?php

namespace Hail\Image\Imagick\Commands;

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
        
        $transparency = $transparency > 0 ? (100 / $transparency) : 1000;

        return $image->getCore()->evaluateImage(\Imagick::EVALUATE_DIVIDE, $transparency, \Imagick::CHANNEL_ALPHA);
    }
}
