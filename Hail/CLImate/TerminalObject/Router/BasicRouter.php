<?php

namespace Hail\CLImate\TerminalObject\Router;

use Hail\CLImate\Util\Helper;
use Hail\CLImate\Util\OutputImporter;

class BasicRouter extends BaseRouter implements RouterInterface
{
    use OutputImporter;

    /**
     * Get the full path for a terminal object class
     *
     * @param  string $class
     *
     * @return string
     */
    public function path($class)
    {
        return $this->getPath('Basic\\' . $this->shortName($class));
    }

    /**
     * Execute a basic terminal object
     *
     * @param \Hail\CLImate\TerminalObject\Basic\BasicTerminalObject $obj
     * @return void
     */
    public function execute($obj)
    {
        $results = Helper::toArray($obj->result());

        $this->output->persist();

        foreach ($results as $result) {
            if ($obj->sameLine()) {
                $this->output->sameLine();
            }

            $this->output->write($obj->getParser()->apply($result));
        }

        $this->output->persist(false);
    }
}
