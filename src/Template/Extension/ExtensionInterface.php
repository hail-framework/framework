<?php

namespace Hail\Template\Extension;

use Hail\Template\Engine;

/**
 * A common interface for extensions.
 */
interface ExtensionInterface
{
    public function register(Engine $engine);
}
