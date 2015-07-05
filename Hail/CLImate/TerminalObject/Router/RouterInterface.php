<?php

namespace Hail\CLImate\TerminalObject\Router;

interface RouterInterface
{
    /**
     * @param $class
     * @return string
     */
    public function path($class);

    /**
     * @param $class
     * @return boolean
     */
    public function exists($class);

    /**
     * @param $obj
     * @return null|\Hail\CLImate\TerminalObject\Dynamic\DynamicTerminalObject
     */
    public function execute($obj);

}
