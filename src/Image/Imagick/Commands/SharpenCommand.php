<?php

namespace Hail\Image\Imagick\Commands;

class SharpenCommand extends \Hail\Image\Commands\AbstractCommand
{
    /**
     * Sharpen image
     *
     * @param  \Hail\Image\Image $image
     * @return bool
     */
    public function execute($image)
    {
        $amount = $this->argument(0)->between(0, 100)->value(10);

        return $image->getCore()->unsharpMaskImage(1, 1, $amount / 6.25, 0);
    }
}
