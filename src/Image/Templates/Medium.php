<?php

namespace Hail\Image\Templates;

use Hail\Image\Image;
use Hail\Image\Filters\FilterInterface;

class Medium implements FilterInterface
{
    public function applyFilter(Image $image)
    {
        return $image->fit(240, 180);
    }
}