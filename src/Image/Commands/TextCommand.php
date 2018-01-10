<?php

namespace Hail\Image\Commands;

use Closure;

class TextCommand extends AbstractCommand
{
    /**
     * Write text on given image
     *
     * @param  \Hail\Image\Image $image
     *
     * @return bool
     */
    public function execute($image)
    {
        $text = $this->argument(0)->required()->value();
        $x = $this->argument(1)->type('numeric')->value(0);
        $y = $this->argument(2)->type('numeric')->value(0);
        $callback = $this->argument(3)->type('closure')->value();

        $namespace = $image->getDriver()->getNamespace();
        $fontclassname = "\{$namespace}\Font";

        $font = new $fontclassname($text);

        if ($callback instanceof Closure) {
            $callback($font);
        }

        $font->applyToImage($image, $x, $y);

        return true;
    }
}
