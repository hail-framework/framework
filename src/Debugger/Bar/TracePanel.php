<?php
/**
 * Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Debugger\Bar;


\defined('XDEBUG_EXTENSION') || \define('XDEBUG_EXTENSION', \extension_loaded('xdebug'));

/**
 * XDebug Trace panel for Nette framework.
 *
 * @author   Miloslav Hůla
 * @version  $Format:%m$
 * @see      http://github.com/milo/XDebugTracePanel
 * @licence  LGPL
 */
class TracePanel implements PanelInterface
{
    /** Tracing states */
    public const
        STATE_STOP = 0,
        STATE_RUN = 1,
        STATE_PAUSE = 2;

    /** Filter callback action bitmask */
    public const
        STOP = 0x01,
        SKIP = 0x02;

    /** Adding filter bitmask flags */
    public const
        FILTER_ENTRY = 1,
        FILTER_EXIT = 2,
        FILTER_BOTH = 3,
        FILTER_APPEND_ENTRY = 4,
        FILTER_APPEND_EXIT = 8,
        FILTER_APPEND = 12,
        FILTER_REPLACE_ENTRY = 16,
        FILTER_REPLACE_EXIT = 32,
        FILTER_REPLACE = 48;

    /** @internal */
    private const
        WRITE_OK = 'write-ok';

    /**
     * @var int  maximal length of line in trace file
     */
    public static $traceLineLength = 4096;

    /**
     * @var bool  delete trace file in destructor or not
     */
    public $deleteTraceFile = false;

    /**
     * @var bool  perform function time statistics
     */
    protected $performStatistics = false;

    /**
     * @var string  by which column sort the statistics
     */
    protected $sortBy = 'averageTime';

    /**
     * @var int  tracing state
     */
    private $state = self::STATE_STOP;

    /**
     * @var string  path to trace file
     */
    private $traceFile;

    /**
     * @var stdClass[]
     */
    protected $traces = [];

    /**
     * @var reference to $this->traces
     */
    protected $trace;

    /**
     * @var string[]  trace titles
     */
    protected $titles = [];

    /**
     * @var array[level => indent size]
     */
    protected $indents = [];

    /**
     * @var reference to $this->indents
     */
    protected $indent;

    /**
     * @var array[function => stdClass]
     */
    protected $statistics = [];

    /**
     * @var reference to $this->statistics
     */
    protected $statistic;

    /**
     * @var bool  internal error occured, error template will be rendered
     */
    protected $isError = false;

    /**
     * @var string
     */
    protected $errMessage = '';

    /**
     * @var string
     */
    protected $errFile;

    /**
     * @var int
     */
    protected $errLine;

    /**
     * @var callback[]  called when entry record from trace file is parsed
     */
    protected $filterEntryCallbacks = [];

    /**
     * @var callback[]  called when exit record from trace file is parsed
     */
    protected $filterExitCallbacks = [];

    /**
     * @var array[setting => bool]  default filter setting
     */
    protected $skipOver = [
        'phpInternals' => true,
        'XDebugTrace' => true,
        'Hail' => true,
        'Composer' => true,
        'callbacks' => true,
        'includes' => true,
    ];


    /**
     * @param  string $traceFile path to trace file, extension .xt is optional
     */
    public function __construct($traceFile)
    {
        if (\strrchr($traceFile, '.') === '.xt') {
            $traceFile = \substr($traceFile, 0, -3);
        }

        if (!XDEBUG_EXTENSION) {
            $this->setError('XDebug extension is not loaded');
        } elseif (@\file_put_contents($traceFile . '.xt', self::WRITE_OK) === false) {
            $this->setError("Cannot create trace file '$traceFile.xt'", \error_get_last());
        } else {
            $this->traceFile = $traceFile;
        }

        $this->addFilterCallback([$this, 'defaultFilterCb']);
    }


    public function __destruct()
    {
        if ($this->deleteTraceFile && \is_file($this->traceFile . '.xt')) {
            @\unlink($this->traceFile . '.xt');
        }
    }

    /**
     * Enable or disable function time statistics.
     *
     * @param  bool  enable statistics
     * @param  string  sort by column 'count', 'deltaTime' or 'averageTime'
     *
     * @return TracePanel
     * @throws \InvalidArgumentException
     */
    public function enableStatistics($enable = true, $sortBy = 'averageTime')
    {
        if (!\in_array($sortBy, ['count', 'deltaTime', 'averageTime'], true)) {
            throw new \InvalidArgumentException("Cannot sort statistics by '$sortBy' column.");
        }

        $this->performStatistics = (bool) $enable;
        $this->sortBy = $sortBy;

        return $this;
    }



    /* ~~~ Start/Pause/Stop tracing part ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
    /**
     * Start or continue tracing.
     *
     * @param  string|NULL trace title
     */
    public function start($title = null)
    {
        if (!$this->isError) {
            if ($this->state === self::STATE_RUN) {
                $this->pause();
            }

            if ($this->state === self::STATE_STOP) {
                $this->titles = [$title];
                \xdebug_start_trace($this->traceFile, XDEBUG_TRACE_COMPUTERIZED);
            } elseif ($this->state === self::STATE_PAUSE) {
                $this->titles[] = $title;
                \xdebug_start_trace($this->traceFile, XDEBUG_TRACE_COMPUTERIZED | XDEBUG_TRACE_APPEND);
            }

            $this->state = self::STATE_RUN;
        }
    }


    /**
     * Pause tracing.
     */
    public function pause()
    {
        if ($this->state === self::STATE_RUN) {
            \xdebug_stop_trace();
            $this->state = self::STATE_PAUSE;
        }
    }


    /**
     * Stop tracing.
     */
    public function stop()
    {
        if ($this->state === self::STATE_RUN) {
            \xdebug_stop_trace();
        }

        $this->state = self::STATE_STOP;
    }



    /*~~~ Rendering part ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */

    /**
     * Template helper converts seconds to ns, us, ms, s.
     *
     * @param  float $time      time interval in seconds
     * @param  int   $precision part precision
     *
     * @return string  formated time
     */
    public function time($time, $precision = 0)
    {
        $units = 's';
        if ($time < 1e-6) {    // <1us
            $units = 'ns';
            $time *= 1e9;

        } elseif ($time < 1e-3) { // <1ms
            $units = "\xc2\xb5s";
            $time *= 1e6;

        } elseif ($time < 1) { // <1s
            $units = 'ms';
            $time *= 1e3;
        }

        return \round($time, $precision) . ' ' . $units;
    }


    /**
     * Template helper converts seconds to HTML class string.
     *
     * @param  float $time time interval in seconds
     * @param  float $slow over this value is interval classified as slow
     * @param  float $fast under this value is interval classified as fast
     *
     * @return string
     */
    public function timeClass($time, $slow = null, $fast = null)
    {
        $slow = $slow ?: 0.02;  // 20ms
        $fast = $fast ?: 1e-3;  //  1ms

        if ($time <= $fast) {
            return 'timeFast';
        }

        if ($time <= $slow) {
            return 'timeMedian';
        }

        return 'timeSlow';
    }

    /**
     * Converts to human readable file size.
     *
     * @param  int
     * @param  int
     *
     * @return string
     */
    public function bytes($bytes, $precision = 2)
    {
        $bytes = \round($bytes);
        $units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB'];
        foreach ($units as $unit) {
            if (\abs($bytes) < 1024 || $unit === \end($units)) {
                break;
            }
            $bytes = $bytes / 1024;
        }

        return \round($bytes, $precision) . ' ' . $unit;
    }


    /**
     * Sets internal error variables.
     *
     * @param  string $message   error message
     * @param  array  $lastError error_get_last()
     */
    protected function setError($message, array $lastError = null)
    {
        $this->isError = true;
        $this->errMessage = $message;

        if ($lastError !== null) {
            $this->errMessage .= ': ' . $lastError['message'];
            $this->errFile = $lastError['file'];
            $this->errLine = $lastError['line'];
        }
    }


    /**
     * Render error message.
     *
     * @return  string  rendered error template
     */
    protected function renderError()
    {
        $errMessage = $this->errMessage;
        $errFile = $this->errFile;
        $errLine = $this->errLine;

        \ob_start();
        require __DIR__ . '/templates/trace.error.phtml';

        return \ob_get_clean();
    }


    /**
     * Implements PanelInterface
     */
    public function getTab()
    {
        \ob_start();
        require __DIR__ . '/templates/trace.tab.phtml';

        return \ob_get_clean();
    }


    /**
     * Implements PanelInterface
     */
    public function getPanel()
    {
        $this->stop();

        if ($this->isError) {
            return $this->renderError();
        }

        $parsingStart = \microtime(true);

        if (($traceFileSize = @\filesize($this->traceFile . '.xt')) <= \strlen(self::WRITE_OK)) {
            if ($traceFileSize === false) {
                $this->setError("Cannot read trace file '$this->traceFile.xt' size", \error_get_last());
            } elseif ($traceFileSize === 0) {
                $this->setError("Trace file '$this->traceFile.xt' is empty");
            } elseif (@\file_get_contents($this->traceFile . '.xt') === self::WRITE_OK) {
                $this->setError('Tracing did not start');
            }
        } elseif (($fd = @\fopen($this->traceFile . '.xt', 'rb')) === false) {
            $this->setError("Cannot open trace file '$this->traceFile.xt'", \error_get_last());
        } elseif (!\preg_match('/^Version: 2\..*/', (string) \fgets($fd, self::$traceLineLength))) {
            $this->setError('Trace file version line mischmasch');
        } elseif (!\preg_match('/^File format: [2-4]/', (string) \fgets($fd, self::$traceLineLength))) {
            $this->setError('Trace file format line mischmasch');
        } else {
            while (($line = \fgets($fd, self::$traceLineLength)) !== false) {
                if (\strpos($line, 'TRACE START') === 0) {    // TRACE START line
                    $this->openTrace();

                } elseif (\strpos($line, 'TRACE END') === 0) {    // TRACE END line
                    $this->closeTrace();

                } elseif ($this->isTraceOpened()) {
                    $line = \rtrim($line, "\r\n");

                    $cols = \explode("\t", $line);
                    if (!\strlen($cols[0]) && \count($cols) === 5) {    // last line before TRACE END
                        /*
                                                $record = (object) [
                                                    'time' => (float) $cols[3],
                                                    'memory' => (float) $cols[4],
                                                ];
                                                $this->addRecord($record, TRUE);
                        */
                        continue;

                    }

                    $record = (object) [
                        'level' => (int) $cols[0],
                        'id' => (float) $cols[1],
                        'isEntry' => !$cols[2],
                        'exited' => false,
                        'time' => (float) $cols[3],
                        'exitTime' => null,
                        'deltaTime' => null,
                        'memory' => (float) $cols[4],
                        'exitMemory' => null,
                        'deltaMemory' => null,
                    ];

                    if ($record->isEntry) {
                        $record->function = $cols[5];
                        $record->isInternal = !$cols[6];
                        $record->includeFile = \strlen($cols[7]) ? $cols[7] : null;
                        $record->filename = $cols[8];
                        $record->line = $cols[9];
                        $record->evalInfo = '';

                        if (\strcmp(\substr($record->filename, -13), "eval()'d code") === 0) {
                            \preg_match('/(.*)\(([0-9]+)\) : eval\(\)\'d code$/', $record->filename, $match);
                            $record->evalInfo = "- eval()'d code ($record->line)";
                            $record->filename = $match[1];
                            $record->line = $match[2];
                        }
                    }

                    $this->addRecord($record);
                }
            }

            $this->closeTrace();  // in case of non-complete trace file
        }

        if ($this->isError) {
            return $this->renderError();
        }

        $traces = new \CachingIterator(
            new \ArrayIterator($this->trace)
        );
        $indents = $this->indents;
        $titles = $this->titles;
        $parsingTime = \microtime(true) - $parsingStart;

        if ($this->performStatistics) {
            $statistics = $this->statistics;
        }

        \ob_start(null, 0, PHP_OUTPUT_HANDLER_REMOVABLE);
        require __DIR__ . '/templates/trace.panel.phtml';

        return \ob_get_clean();
    }


    /**
     * Sets trace and indent references.
     */
    protected function openTrace()
    {
        $index = \count($this->traces);

        $this->traces[$index] = [];
        $this->trace =& $this->traces[$index];

        $this->indents[$index] = [];
        $this->indent =& $this->indents[$index];

        if ($this->performStatistics) {
            $this->statistics[$index] = [];
            $this->statistic =& $this->statistics[$index];
        }
    }


    /**
     * Unset trace and indent references and compute indents.
     */
    protected function closeTrace()
    {
        if ($this->trace !== null) {
            foreach ($this->trace as $id => $record) {
                if (!$record->exited) {    // last chance to filter non-exited records by FILTER_EXIT callback
                    $remove = false;
                    foreach ($this->filterExitCallbacks as $callback) {
                        $result = (int) $callback($record, false, $this);
                        if ($result & self::SKIP) {
                            $remove = true;
                        }

                        if ($result & self::STOP) {
                            break;
                        }
                    }

                    if ($remove) {
                        unset($this->trace[$id]);
                        continue;
                    }
                }

                $this->indent[$record->level] = 1;
            }

            if (null !== $this->indent) {
                \ksort($this->indent);
                $keys = \array_keys($this->indent);
                $this->indent = \array_combine($keys, \array_keys($keys));
            }

            $null = null;
            $this->trace =& $null;
            $this->indent =& $null;

            if ($this->performStatistics) {
                foreach ($this->statistic as $statistic) {
                    $statistic->averageTime = $statistic->deltaTime / $statistic->count;
                }

                $sortBy = $this->sortBy;
                \uasort($this->statistic, function ($a, $b) use ($sortBy) {
                    return $a->{$sortBy} < $b->{$sortBy};
                });

                $this->statistic =& $null;
            }
        }
    }


    /**
     * Check if internal references are sets.
     *
     * @return bool
     */
    protected function isTraceOpened()
    {
        return $this->trace !== null;
    }


    /**
     * Push parsed trace file line into trace stack.
     *
     * @param  \stdClass parsed trace file line
     */
    protected function addRecord(\stdClass $record)
    {
        if ($record->isEntry) {
            $add = true;
            foreach ($this->filterEntryCallbacks as $callback) {
                $result = (int) $callback($record, true, $this);
                if ($result & self::SKIP) {
                    $add = false;
                }

                if ($result & self::STOP) {
                    break;
                }
            }

            if ($add) {
                $this->trace[$record->id] = $record;
            }

        } elseif (isset($this->trace[$record->id])) {
            $entryRecord = $this->trace[$record->id];

            $entryRecord->exited = true;
            $entryRecord->exitTime = $record->time;
            $entryRecord->deltaTime = $record->time - $entryRecord->time;
            $entryRecord->exitMemory = $record->memory;
            $entryRecord->deltaMemory = $record->memory - $entryRecord->memory;

            $remove = false;
            foreach ($this->filterExitCallbacks as $callback) {
                $result = (int) $callback($entryRecord, false, $this);
                if ($result & self::SKIP) {
                    $remove = true;
                }

                if ($result & self::STOP) {
                    break;
                }
            }

            if ($remove) {
                unset($this->trace[$record->id]);

            } elseif ($this->performStatistics) {
                if (!isset($this->statistic[$entryRecord->function])) {
                    $this->statistic[$entryRecord->function] = (object) [
                        'count' => 1,
                        'deltaTime' => $entryRecord->deltaTime,
                    ];

                } else {
                    $this->statistic[$entryRecord->function]->count += 1;
                    $this->statistic[$entryRecord->function]->deltaTime += $entryRecord->deltaTime;
                }
            }
        }
    }



    /* ~~~ Trace records filtering ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
    /**
     * Default filter (self::defaultFilterCb()) setting.
     *
     * @param  string $type
     * @param  bool   $skip skip or not
     *
     * @return TracePanel
     * @throws \InvalidArgumentException
     */
    public function skip($type, $skip)
    {
        if (!\array_key_exists($type, $this->skipOver)) {
            throw new \InvalidArgumentException("Unknown skip type '$type'. Use one of [" . \implode(', ',
                    \array_keys($this->skipOver)) . ']');
        }

        $this->skipOver[$type] = (bool) $skip;

        return $this;
    }


    /**
     * Shortcut to self::skip('phpInternals', bool)
     *
     * @param  bool $skip skip PHP internal functions?
     *
     * @return TracePanel
     * @throws \InvalidArgumentException
     */
    public function skipInternals($skip)
    {
        return $this->skip('phpInternals', $skip);
    }


    /**
     * Default filtering callback.
     *
     * @param  \stdClass trace file record
     *
     * @return int  bitmask of self::SKIP, self::STOP
     */
    protected function defaultFilterCb(\stdClass $record)
    {
        if ($this->skipOver['phpInternals'] && $record->isInternal) {
            return self::SKIP;
        }

        if ($this->skipOver['XDebugTrace']) {
            if ($record->filename === __FILE__) {
                return self::SKIP;
            }

            if (\strpos($record->function, 'XDebug::') === 0) {
                return self::SKIP;
            }
        }

        if ($this->skipOver['Hail'] && \strpos($record->function, 'Hail\\') === 0) {
            return self::SKIP;
        }

        if ($this->skipOver['Composer'] && \strpos($record->function, 'Composer\\') === 0) {
            return self::SKIP;
        }

        if ($this->skipOver['callbacks'] && ($record->function === 'callback' || $record->function === '{closure}')) {
            return self::SKIP;
        }

        if ($this->skipOver['includes'] && $record->includeFile !== null) {
            return self::SKIP;
        }

        return 0;
    }


    /**
     * Register own filter callback.
     *
     * @param  callback (\stdClass $record, bool $isEntry, XDebugPanel $this)
     * @param  int $flags bitmask of self::FILTER_*
     */
    public function addFilterCallback($callback, $flags = null)
    {
        $flags = (int) $flags;

        if ($flags & self::FILTER_REPLACE_ENTRY) {
            $this->filterEntryCallbacks = [];
        }

        if ($flags & self::FILTER_REPLACE_EXIT) {
            $this->filterExitCallbacks = [];
        }

        // Called when entry records came
        if (($flags & self::FILTER_ENTRY) || !($flags & self::FILTER_EXIT)) {
            if ($flags & self::FILTER_APPEND_ENTRY) {
                $this->filterEntryCallbacks[] = $callback;

            } else {
                \array_unshift($this->filterEntryCallbacks, $callback);
            }
        }

        // Called when exit records came
        if ($flags & self::FILTER_EXIT) {
            if ($flags & self::FILTER_APPEND_EXIT) {
                $this->filterExitCallbacks[] = $callback;

            } else {
                \array_unshift($this->filterExitCallbacks, $callback);
            }
        }
    }


    /**
     * Replace all filter callbacks by this one.
     *
     * @param  callback (\stdClass $record, bool $isEntry, XDebugPanel $this)
     * @param  int $flags bitmask of self::FILTER_*
     */
    public function setFilterCallback($callback, $flags = null)
    {
        $flags = ((int) $flags) | self::FILTER_REPLACE;
        $this->addFilterCallback($callback, $flags);
    }



    /* ~~~ Filtering callback shortcuts ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~ */
    /**
     * Trace all.
     */
    public function traceAll()
    {
        $this->filterEntryCallbacks = [];
        $this->filterExitCallbacks = [];
    }


    /**
     * Trace function by name.
     *
     * @param  string|array $name          name of function or pair [class, method]
     * @param  bool         $deep          show inside function trace too
     * @param  bool         $showInternals show internals in inside function trace
     */
    public function traceFunction($name, $deep = false, $showInternals = false)
    {
        if (\is_array($name)) {
            $name1 = \implode('::', $name);
            $name2 = \implode('->', $name);
        } else {
            $name1 = $name2 = (string) $name;
        }

        $cb = function (\stdClass $record, $isEntry) use ($name1, $name2, $deep, $showInternals) {
            static $cnt = 0;

            if ($record->function === $name1 || $record->function === $name2) {
                $cnt += $isEntry ? 1 : -1;

                return null;
            }

            return ($deep && $cnt && ($showInternals || !$record->isInternal)) ? null : self::SKIP;
        };

        $this->setFilterCallback($cb, self::FILTER_BOTH);
    }


    /**
     * Trace function which name is expressed by PCRE reqular expression.
     *
     * @param  string $re            regular expression
     * @param  bool   $deep          show inside function trace too
     * @param  bool   $showInternals show internals in inside function trace
     */
    public function traceFunctionRe($re, $deep = false, $showInternals = false)
    {
        $cb = function (\stdClass $record, $isEntry) use ($re, $deep, $showInternals) {
            static $cnt = 0;

            if (\preg_match($re, $record->function)) {
                $cnt += $isEntry ? 1 : -1;

                return null;
            }

            return ($deep && $cnt && ($showInternals || !$record->isInternal)) ? null : self::SKIP;
        };

        $this->setFilterCallback($cb, self::FILTER_BOTH);
    }


    /**
     * Trace functions running over/under the time.
     *
     * @param  float|string $delta delta time
     * @param  bool         $over  TRUE = over the delta time, FALSE = under the delta time
     */
    public function traceDeltaTime($delta, $over = true)
    {
        if (\is_string($delta)) {
            static $units = [
                'ns' => 1e-9,
                'us' => 1e-6,
                'ms' => 1e-3,
                's' => 1,
            ];

            foreach ($units as $unit => $multipler) {
                $length = \strlen($unit);
                if (\substr_compare($delta, $unit, -$length, $length, true) === 0) {
                    $delta = \substr($delta, 0, -$length) * $multipler;
                    break;
                }
            }
        }
        $delta = (float) $delta;

        $cb = function (\stdClass $record) use ($delta, $over) {
            if ($over) {
                if ($record->deltaTime < $delta) {
                    return self::SKIP;
                }
            }

            if ($record->deltaTime > $delta) {
                return self::SKIP;
            }

            return 0;
        };

        $this->setFilterCallback($cb, self::FILTER_EXIT);
    }


    /**
     * Trace functions which consumes over/under the memory.
     *
     * @param  float|string $delta delta memory
     * @param  bool         $over  TRUE = over the delta memory, FALSE = under the delta memory
     */
    public function traceDeltaMemory($delta, $over = true)
    {
        if (\is_string($delta)) {
            static $units = [
                'MB' => 1048576, // 1024 * 1024
                'kB' => 1024,
                'B' => 1,
            ];

            foreach ($units as $unit => $multipler) {
                $length = \strlen($unit);
                if (\substr_compare($delta, $unit, -$length, $length, true) === 0) {
                    $delta = \substr($delta, 0, -$length) * $multipler;
                    break;
                }
            }
        }
        $delta = (float) $delta;

        $cb = function (\stdClass $record) use ($delta, $over) {
            if ($over) {
                if ($record->deltaMemory < $delta) {
                    return self::SKIP;
                }
            }

            if ($record->deltaMemory > $delta) {
                return self::SKIP;
            }

            return 0;
        };

        $this->setFilterCallback($cb, self::FILTER_EXIT);
    }
}
