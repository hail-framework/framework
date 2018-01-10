<?php

namespace Hail\Image\Gd\Shapes;

use Hail\Image\Image;

class CircleShape extends EllipseShape
{
    /**
     * Diameter of circle in pixels
     *
     * @var int
     */
    public $diameter = 100;

    /**
     * Create new instance of circle
     *
     * @param int $diameter
     */
    public function __construct($diameter = null)
    {
        $diameter = is_numeric($diameter) ? (int) $diameter : $this->diameter;

        $this->width = $diameter;
        $this->height = $diameter;
        $this->diameter = $diameter;
    }

    /**
     * Draw current circle on given image
     *
     * @param  Image   $image
     * @param  integer $x
     * @param  integer $y
     * @return bool
     */
    public function applyToImage(Image $image, $x = 0, $y = 0)
    {
        return parent::applyToImage($image, $x, $y);
    }
}
