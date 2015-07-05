<?php

namespace Hail\CLImate\TerminalObject\Basic;

use Hail\CLImate\Decorator\Parser\ParserImporter;
use Hail\CLImate\Settings\SettingsImporter;
use Hail\CLImate\Util\UtilImporter;

abstract class BasicTerminalObject implements BasicTerminalObjectInterface
{
    use SettingsImporter, ParserImporter, UtilImporter;

    /**
     * Set the property if there is a valid value
     *
     * @param string $key
     * @param string $value
     */
    protected function set($key, $value)
    {
        if (strlen($value)) {
            $this->$key = $value;
        }
    }

    /**
     * Get the parser for the current object
     *
     * @return \Hail\CLImate\Decorator\Parser\Parser
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * Check if this object requires a new line to be added after the output
     *
     * @return boolean
     */
    public function sameLine()
    {
        return false;
    }

}
