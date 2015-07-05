<?php

namespace Hail\CLImate\Decorator\Parser;

trait ParserImporter
{
    /**
     * An instance of the Parser class
     *
     * @var \Hail\CLImate\Decorator\Parser\Parser $parser
     */
    protected $parser;

    /**
     * Import the parser and set the property
     *
     * @param \Hail\CLImate\Decorator\Parser\Parser $parser
     */
    public function parser(Parser $parser)
    {
        $this->parser = $parser;
    }
}
