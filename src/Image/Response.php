<?php

namespace Hail\Image;

use Hail\Http\Factory;
use Hail\Util\MimeType;

class Response
{
    /**
     * Image that should be displayed by response
     *
     * @var Image
     */
    public $image;

    /**
     * Format of displayed image
     *
     * @var string
     */
    public $format;

    /**
     * Quality of displayed image
     *
     * @var int
     */
    public $quality;

    /**
     * Creates a new instance of response
     *
     * @param Image   $image
     * @param string  $format
     * @param int $quality
     */
    public function __construct(Image $image, $format = null, $quality = null)
    {
        $this->image = $image;
        $this->format = $format ? $format : $image->mime;
        $this->quality = $quality ? $quality : 90;
    }

    /**
     * Builds response according to settings
     *
     * @return mixed
     */
    public function make()
    {
        $this->image->encode($this->format, $this->quality);
        $data = $this->image->getEncoded();

        $mime = MimeType::getMimeTypeByContent($data);
        $length = \strlen($data);

        return Factory::response(200, $data, [
            'Content-Type' => $mime,
            'Content-Length' => $length,
        ]);
    }
}
