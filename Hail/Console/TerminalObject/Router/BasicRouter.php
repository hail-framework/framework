<?php
namespace Hail\Console\TerminalObject\Router;

use Hail\Console\Util\Helper;

class BasicRouter extends AbstractRouter
{
    /**
     * @return string
     */
    public function pathPrefix()
    {
        return 'Basic';
    }

    /**
     * Execute a basic terminal object
     *
     * @param \Hail\Console\TerminalObject\Basic\AbstractBasic $obj
     * @return void
     */
    public function execute($obj)
    {
        $results = Helper::toArray($obj->result());

        $this->output->persist();

	    if ($obj->sameLine()) {
		    $this->output->sameLine();
	    }

        foreach ($results as $result) {
            $this->output->write($obj->getParser()->apply($result));
        }

        $this->output->persist(false);
    }
}
