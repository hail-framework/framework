<?php

namespace Hail\Image\Commands;

use Hail\Image\Response;

class ResponseCommand extends AbstractCommand
{
    /**
     * Builds HTTP response from given image
     *
     * @param  \Hail\Image\Image $image
     *
     * @return bool
     */
    public function execute($image)
    {
        $format = $this->argument(0)->value();
        $quality = $this->argument(1)->between(0, 100)->value();

        $response = new Response($image, $format, $quality);

        $this->setOutput($response->make());

        return true;
    }
}
