<?php
namespace Hail\DI;

class Pimple extends Pimple\Container
{
    public function set($id, $value)
    {
        $this->offsetSet($id, $value);
    }

    public function __call($func, $args)
    {
        if (isset($args[0]) && $args[0] instanceof \Closure) {
            $this->extend($func, $args[0]);
        }
        return $this->offsetGet($func);
    }
}
