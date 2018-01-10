<?php

namespace Hail\Image\Commands;

use Hail\Http\Factory;

class PsrResponseCommand extends AbstractCommand
{
    /**
     * Builds PSR7 compatible response. May replace "response" command in
     * some future.
     *
     * Method will generate binary stream and put it inside PSR-7
     * ResponseInterface. Following code can be optimized using native php
     * streams and more "clean" streaming, however drivers has to be updated
     * first.
     *
     * @param  \Hail\Image\Image $image
     *
     * @return bool
     */
    public function execute($image)
    {
        $format = $this->argument(0)->value();
        $quality = $this->argument(1)->between(0, 100)->value();

        //Encoded property will be populated at this moment
        $stream = $image->stream($format, $quality);

        $mimetype = finfo_buffer(
            finfo_open(FILEINFO_MIME_TYPE),
            $image->getEncoded()
        );

        $this->setOutput(Factory::response(200, $stream, [
            'Content-Type' => $mimetype,
            'Content-Length' => strlen($image->getEncoded()),
        ]));

        return true;
    }
}