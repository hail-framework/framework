<?php
namespace Hail\Facades;

use Hail\Tracy\Bar\TracePanel;

/**
 * Class Trace
 *
 * @package Hail\Facades
 *
 * @method static TracePanel enableStatistics($enable = TRUE, $sortBy = 'averageTime')
 * @method static start($title = NULL)
 * @method static pause()
 * @method static stop()
 * @method static string time(float $time, int $precision = 0)
 * @method static string timeClass($time, $slow = NULL, $fast = NULL)
 * @method static string bytes($bytes, $precision = 2)
 * @method static TracePanel skip(string $type, bool $skip)
 * @method static TracePanel skipInternals(bool $skip)
 * @method static addFilterCallback($callback, $flags = NULL)
 * @method static setFilterCallback($callback, $flags = NULL)
 * @method static traceAll()
 * @method static traceFunction($name, $deep = FALSE, $showInternals = FALSE)
 * @method static traceFunctionRe($re, $deep = FALSE, $showInternals = FALSE)
 * @method static traceDeltaTime($delta, $over = TRUE)
 * @method static traceDeltaMemory($delta, $over = TRUE)
 *
 */
class Trace extends Facade
{
	protected static function instance()
	{
		return new TracePanel(
			TEMP_PATH . 'xdebugTrace.xt'
		);
	}
}