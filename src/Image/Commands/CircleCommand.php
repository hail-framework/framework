<?php

namespace Hail\Image\Commands;

use Closure;

class CircleCommand extends AbstractCommand
{
    /**
     * Draw a circle centered on given image
     *
     * @param  \Hail\Image\image $image
     *
     * @return bool
     */
    public function execute($image)
    {
        $diameter = $this->argument(0)->type('numeric')->required()->value();
        $x = $this->argument(1)->type('numeric')->required()->value();
        $y = $this->argument(2)->type('numeric')->required()->value();
        $callback = $this->argument(3)->type('closure')->value();

        $namespace = $image->getDriver()->getNamespace();
        $circleClassname = "\{$namespace}\Shapes\CircleShape";

        $circle = new $circleClassname($diameter);

        if ($callback instanceof Closure) {
            $callback($circle);
        }

        $circle->applyToImage($image, $x, $y);

        return true;
    }
}
