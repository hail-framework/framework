<?php

namespace Hail\Console\Util;

use Hail\Console\Decorator\Parser\AbstractParser;

trait ParserImportTrait
{
    /**
     * An instance of the Parser class
     *
     * @var AbstractParser $parser
     */
    protected $parser;

    /**
     * Import the parser and set the property
     *
     * @param AbstractParser $parser
     */
    public function parser(AbstractParser $parser)
    {
        $this->parser = $parser;
    }
}
