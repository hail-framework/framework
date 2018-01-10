<?php

namespace Hail\Image\Imagick;

use Hail\Image\Image;
use Hail\Image\AbstractFont;

class Font extends AbstractFont
{
    /**
     * Draws font to given image at given position
     *
     * @param  Image   $image
     * @param  integer $posx
     * @param  integer $posy
     *
     * @return void
     */
    public function applyToImage(Image $image, $posx = 0, $posy = 0)
    {
        // build draw object
        $draw = new \ImagickDraw();
        $draw->setStrokeAntialias(true);
        $draw->setTextAntialias(true);

        // set font file
        if ($this->hasApplicableFontFile()) {
            $draw->setFont($this->file);
        } else {
            throw new \Hail\Image\Exception\RuntimeException(
                "Font file must be provided to apply text to image."
            );
        }

        // parse text color
        $color = new Color($this->color);

        $draw->setFontSize($this->size);
        $draw->setFillColor($color->getPixel());

        // align horizontal
        switch ($this->align) {
            case 'center':
                $align = \Imagick::ALIGN_CENTER;
                break;

            case 'right':
                $align = \Imagick::ALIGN_RIGHT;
                break;

            default:
                $align = \Imagick::ALIGN_LEFT;
                break;
        }

        $draw->setTextAlignment($align);

        // align vertical
        if ($this->valign !== 'bottom') {

            // calculate box size
            $dimensions = $image->getCore()->queryFontMetrics($draw, $this->text);

            // corrections on y-position
            switch ($this->valign) {
                case 'center':
                case 'middle':
                    $posy += $dimensions['textHeight'] * 0.65 / 2;
                    break;

                case 'top':
                    $posy += $dimensions['textHeight'] * 0.65;
                    break;
            }
        }

        // apply to image
        $image->getCore()->annotateImage($draw, $posx, $posy, $this->angle * (-1), $this->text);
    }

    /**
     * Calculates bounding box of current font setting
     *
     * @return array
     */
    public function getBoxSize()
    {
        $box = [];

        // build draw object
        $draw = new \ImagickDraw();
        $draw->setStrokeAntialias(true);
        $draw->setTextAntialias(true);

        // set font file
        if ($this->hasApplicableFontFile()) {
            $draw->setFont($this->file);
        } else {
            throw new \Hail\Image\Exception\RuntimeException(
                "Font file must be provided to apply text to image."
            );
        }

        $draw->setFontSize($this->size);

        $dimensions = (new \Imagick())->queryFontMetrics($draw, $this->text);

        if ($this->text === '') {
            // no text -> no boxsize
            $box['width'] = 0;
            $box['height'] = 0;
        } else {
            // get boxsize
            $box['width'] = (int) abs($dimensions['textWidth']);
            $box['height'] = (int) abs($dimensions['textHeight']);
        }

        return $box;
    }
}
