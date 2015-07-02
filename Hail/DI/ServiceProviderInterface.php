<?php
/**
 * @from https://github.com/silexphp/Pimple
 * Copyright (c) 2009 Fabien Potencier Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\DI;

if (interface_exists('Pimple\\ServiceProviderInterface', false)) {
    interface ServiceProviderInterface extends Pimple\ServiceProviderInterface
    {

    }
    return;
}

/**
 * Pimple service provider interface.
 *
 * @author  Fabien Potencier
 * @author  Dominik Zogg
 */
interface ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Pimple $pimple A container instance
     */
    public function register(Pimple $pimple);
}