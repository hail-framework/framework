<?php

namespace Hail\Console\TerminalObject\Dynamic;

use Hail\Console\Util\{
	ParserImportTrait, OutputImportTrait, UtilImportTrait
};

/**
 * The dynamic terminal object doesn't adhere to the basic terminal object
 * contract, which is why it gets its own base class
 */

abstract class AbstractDynamic
{
    use ParserImportTrait, OutputImportTrait, UtilImportTrait;
}
