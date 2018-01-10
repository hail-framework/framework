<?php

namespace Hail\Image\Gd\Shapes;

use Hail\Image\Image;
use Hail\Image\Gd\Color;

class LineShape extends \Hail\Image\AbstractShape
{
    /**
     * Starting point x-coordinate of line
     *
     * @var int
     */
    public $x = 0;

    /**
     * Starting point y-coordinate of line
     *
     * @var int
     */
    public $y = 0;

    /**
     * Color of line
     *
     * @var string
     */
    public $color = '#000000';

    /**
     * Width of line in pixels
     *
     * @var int
     */
    public $width = 1;

    /**
     * Create new line shape instance
     *
     * @param int $x
     * @param int $y
     */
    public function __construct($x = null, $y = null)
    {
        $this->x = is_numeric($x) ? intval($x) : $this->x;
        $this->y = is_numeric($y) ? intval($y) : $this->y;
    }

    /**
     * Set current line color
     *
     * @param  string $color
     * @return void
     */
    public function color($color)
    {
        $this->color = $color;
    }

    /**
     * Set current line width in pixels
     *
     * @param  integer $width
     * @return void
     */
    public function width($width)
    {
        throw new \Hail\Image\Exception\NotSupportedException(
            "Line width is not supported by GD driver."
        );
    }

    /**
     * Draw current instance of line to given endpoint on given image
     *
     * @param  Image   $image
     * @param  integer $x
     * @param  integer $y
     * @return bool
     */
    public function applyToImage(Image $image, $x = 0, $y = 0)
    {
        $color = new Color($this->color);
        imageline($image->getCore(), $x, $y, $this->x, $this->y, $color->getInt());

        return true;
    }
}
