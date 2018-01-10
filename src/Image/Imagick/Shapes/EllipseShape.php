<?php

namespace Hail\Image\Imagick\Shapes;

use Hail\Image\Image;
use Hail\Image\Imagick\Color;

class EllipseShape extends \Hail\Image\AbstractShape
{
    /**
     * Width of ellipse in pixels
     *
     * @var int
     */
    public $width = 100;

    /**
     * Height of ellipse in pixels
     *
     * @var int
     */
    public $height = 100;

    /**
     * Create new ellipse instance
     *
     * @param int $width
     * @param int $height
     */
    public function __construct($width = null, $height = null)
    {
        $this->width = is_numeric($width) ? (int) $width : $this->width;
        $this->height = is_numeric($height) ? (int) $height : $this->height;
    }

    /**
     * Draw ellipse instance on given image
     *
     * @param  Image   $image
     * @param  integer $x
     * @param  integer $y
     * @return bool
     */
    public function applyToImage(Image $image, $x = 0, $y = 0)
    {
        $circle = new \ImagickDraw;

        // set background
        $bgcolor = new Color($this->background);
        $circle->setFillColor($bgcolor->getPixel());

        // set border
        if ($this->hasBorder()) {
            $border_color = new Color($this->border_color);
            $circle->setStrokeWidth($this->border_width);
            $circle->setStrokeColor($border_color->getPixel());
        }

        $circle->ellipse($x, $y, $this->width / 2, $this->height / 2, 0, 360);

        $image->getCore()->drawImage($circle);

        return true;
    }
}
