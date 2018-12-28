<?php

namespace Hail\Database\Sql;

class Raw
{
    /**
     * @var array
     */
    public $map;

    /**
     * @var string
     */
    public $value;

    public function __construct(string $value, array $map = [])
    {
        $this->value = \trim($value);
        $this->map = $map;
    }
}