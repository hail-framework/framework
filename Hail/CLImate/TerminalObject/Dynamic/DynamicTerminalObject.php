<?php

namespace Hail\CLImate\TerminalObject\Dynamic;

use Hail\CLImate\Decorator\Parser\ParserImporter;
use Hail\CLImate\Settings\SettingsImporter;
use Hail\CLImate\Util\OutputImporter;
use Hail\CLImate\Util\UtilImporter;

/**
 * The dynamic terminal object doesn't adhere to the basic terminal object
 * contract, which is why it gets its own base class
 */

abstract class DynamicTerminalObject
{
    use SettingsImporter, ParserImporter, OutputImporter, UtilImporter;
}
