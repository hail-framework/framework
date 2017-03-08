<?php

namespace Hail\Console\TerminalObject\Basic;

class Br extends AbstractRepeatable
{
    /**
     * Return an empty string
     *
     * @return string
     */
    public function result()
    {
        return array_fill(0, $this->count, '');
    }
}
