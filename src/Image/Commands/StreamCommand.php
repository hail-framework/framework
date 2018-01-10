<?php

namespace Hail\Image\Commands;

use Hail\Http\Factory;

class StreamCommand extends AbstractCommand
{
    /**
     * Builds PSR7 stream based on image data. Method uses Guzzle PSR7
     * implementation as easiest choice.
     *
     * @param  \Hail\Image\Image $image
     *
     * @return bool
     */
    public function execute($image)
    {
        $format = $this->argument(0)->value();
        $quality = $this->argument(1)->between(0, 100)->value();

        $this->setOutput(Factory::stream(
            $image->encode($format, $quality)->getEncoded()
        ));

        return true;
    }
}