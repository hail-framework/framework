<?php

namespace Hail\Image\Commands;

use Closure;

class EllipseCommand extends AbstractCommand
{
    /**
     * Draws ellipse on given image
     *
     * @param  \Hail\Image\Image $image
     *
     * @return bool
     */
    public function execute($image)
    {
        $width = $this->argument(0)->type('numeric')->required()->value();
        $height = $this->argument(1)->type('numeric')->required()->value();
        $x = $this->argument(2)->type('numeric')->required()->value();
        $y = $this->argument(3)->type('numeric')->required()->value();
        $callback = $this->argument(4)->type('closure')->value();

        $namespace = $image->getDriver()->getNamespace();
        $ellipse_classname = "\{$namespace}\Shapes\EllipseShape";

        $ellipse = new $ellipse_classname($width, $height);

        if ($callback instanceof Closure) {
            $callback($ellipse);
        }

        $ellipse->applyToImage($image, $x, $y);

        return true;
    }
}
