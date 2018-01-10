<?php

namespace Hail\Image\Imagick\Commands;

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
        $amount = $this->argument(0)->between(0, 100)->value(1);

        return $image->getCore()->blurImage(1 * $amount, 0.5 * $amount);
    }
}
