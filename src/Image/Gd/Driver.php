<?php

namespace Hail\Image\Gd;

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
        // create empty resource
        $core = imagecreatetruecolor($width, $height);
        $image = new Image(new static, $core);

        // set background color
        $background = new Color($background);
        imagefill($image->getCore(), 0, 0, $background->getInt());

        return $image;
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

    /**
     * Returns clone of given core
     *
     * @return mixed
     */
    public function cloneCore($core)
    {
        $width = imagesx($core);
        $height = imagesy($core);
        $clone = imagecreatetruecolor($width, $height);
        imagealphablending($clone, false);
        imagesavealpha($clone, true);
        $transparency = imagecolorallocatealpha($clone, 0, 0, 0, 127);
        imagefill($clone, 0, 0, $transparency);
        
        imagecopy($clone, $core, 0, 0, 0, 0, $width, $height);

        return $clone;
    }

    public function getNamespace()
    {
        return __NAMESPACE__;
    }
}
