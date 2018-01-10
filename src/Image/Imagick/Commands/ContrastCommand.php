<?php

namespace Hail\Image\Imagick\Commands;

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

        return $image->getCore()->sigmoidalContrastImage($level > 0, $level / 4, 0);
    }
}
