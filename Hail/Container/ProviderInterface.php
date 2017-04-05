<?php

namespace Hail\Container;

/**
 * This interface enables you to package service definitions for reuse.
 *
 * @see Container::register()
 */
interface ProviderInterface
{
    /**
     * Registers services and components with a given `ContainerFactory`
     *
     * @param Container $container
     *
     * @return void
     */
    public function register(Container $container);
}
