<?php

namespace Hail\Filesystem\Adapter\Polyfill;

trait NotSupportingVisibilityTrait
{
    /**
     * Get the visibility of a file.
     *
     * @param string $path
     */
    public function getVisibility($path)
    {
        throw new \LogicException(static::class . ' does not support visibility. Path: ' . $path);
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path
     * @param string $visibility
     */
    public function setVisibility($path, $visibility)
    {
        throw new \LogicException(static::class . ' does not support visibility. Path: ' . $path . ', visibility: ' . $visibility);
    }
}
