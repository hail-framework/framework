<?php
namespace Hail\Console\TerminalObject\Router;


class DynamicRouter extends AbstractRouter
{
    /**
     * @return string
     */
    public function pathPrefix()
    {
        return 'Dynamic';
    }

    /**
     * Execute a dynamic terminal object using given arguments
     *
     * @param \Hail\Console\TerminalObject\Dynamic\AbstractDynamic $obj
     *
     * @return \Hail\Console\TerminalObject\Dynamic\AbstractDynamic
     */
    public function execute($obj)
    {
        $obj->output($this->output);

        return $obj;
    }
}
