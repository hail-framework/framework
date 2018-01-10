<?php

namespace Hail\Image\Imagick;

use Hail\Image\AbstractDriver;
use Hail\Image\AbstractColor;
use Hail\Image\Image;

class Driver extends AbstractDriver
{
    /**
     * Creates new instance of driver
     */
    public function __construct()
    {
        $this->decoder = new Decoder;
        $this->encoder = new Encoder;
    }

    /**
     * Creates new image instance
     *
     * @param  integer $width
     * @param  integer $height
     * @param  mixed   $background
     * @return \Hail\Image\Image
     */
    public function newImage($width, $height, $background = null)
    {
        $background = new Color($background);

        // create empty core
        $core = new \Imagick;
        $core->newImage($width, $height, $background->getPixel(), 'png');
        $core->setType(\Imagick::IMGTYPE_UNDEFINED);
        $core->setImageType(\Imagick::IMGTYPE_UNDEFINED);
        $core->setColorspace(\Imagick::COLORSPACE_UNDEFINED);

        // build image
        return new Image(new static, $core);
    }

    /**
     * Reads given string into color object
     *
     * @param  string $value
     * @return AbstractColor
     */
    public function parseColor($value)
    {
        return new Color($value);
    }

    public function getNamespace()
    {
        return __NAMESPACE__;
    }
}
