<?php

namespace Hail\Console\IO;

class NullStty implements SttyInterface
{
    public function enableEcho()
    {
    }

    public function disableEcho()
    {
    }

    public function dump()
    {
        return '';
    }

    public function withoutEcho(\Closure $callback)
    {
        return $callback();
    }
}
