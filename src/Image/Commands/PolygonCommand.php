<?php

namespace Hail\Image\Commands;

use Closure;

class PolygonCommand extends AbstractCommand
{
    /**
     * Draw a polygon on given image
     *
     * @param  \Hail\Image\image $image
     *
     * @return bool
     */
    public function execute($image)
    {
        $points = $this->argument(0)->type('array')->required()->value();
        $callback = $this->argument(1)->type('closure')->value();

        $vertices_count = count($points);

        // check if number if coordinates is even
        if ($vertices_count % 2 !== 0) {
            throw new \Hail\Image\Exception\InvalidArgumentException(
                "The number of given polygon vertices must be even."
            );
        }

        if ($vertices_count < 6) {
            throw new \Hail\Image\Exception\InvalidArgumentException(
                "You must have at least 3 points in your array."
            );
        }

        $namespace = $image->getDriver()->getNamespace();
        $polygon_classname = "\{$namespace}\Shapes\PolygonShape";

        $polygon = new $polygon_classname($points);

        if ($callback instanceof Closure) {
            $callback($polygon);
        }

        $polygon->applyToImage($image);

        return true;
    }
}
