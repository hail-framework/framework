<?php
/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Debugger;

use Hail\Http\Exception\HttpErrorException;
use Hail\Http\Factory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

\defined('XDEBUG_EXTENSION') || \define('XDEBUG_EXTENSION', \extension_loaded('xdebug'));

/**
 * Debugger: displays and logs errors.
 *
 */
class Debugger
{
    public const VERSION = '2.4.10';

    /** server modes for Debugger::enable() */
    public const
        DEVELOPMENT = false,
        PRODUCTION = true,
        DETECT = null;

    private const COOKIE_SECRET = 'tracy-debug';

    /** @var bool|string|array|null mode defined by environment setting */
    private static $mode = self::DETECT;

    private static $iniSet = true;

    /** @var ServerRequestInterface */
    private static $request;

    /** @var bool in production mode is suppressed any debugging output */
    public static $productionMode = self::DETECT;

    /** @var bool */
    private static $enabled = 0;
    private static $enabledTime;

    /** @var bool */
    private static $started = false;

    /** @var string reserved memory; also prevents double rendering */
    private static $reserved;

    /** @var int initial output buffer level */
    private static $obLevel;

    /********************* errors and exceptions reporting ****************d*g**/

    /** @var bool|int determines whether any error will cause immediate death in development mode; if integer that it's matched against error severity */
    public static $strictMode = false;

    /** @var bool disables the @ (shut-up) operator so that notices and warnings are no longer hidden */
    public static $scream = false;

    /********************* Debugger::dump() ****************d*g**/

    /** @var int  how many nested levels of array/object properties display by dump() */
    public static $maxDepth = 5;

    /** @var int  how long strings display by dump() */
    public static $maxLength = 150;

    /** @var bool display location by dump()? */
    public static $showLocation = false;

    /********************* logging ****************d*g**/

    /** @var string name of the directory where errors should be logged */
    private static $logDirectory;

    /** @var int  log bluescreen in production mode for this error severity */
    public static $logSeverity = 0;

    /** @var string|array email(s) to which send error notifications */
    private static $email;

    /** for LoggerInterface */
    public const
        DEBUG = LogLevel::DEBUG,
        INFO = LogLevel::INFO,
        NOTICE = LogLevel::NOTICE,
        WARNING = LogLevel::WARNING,
        ERROR = LogLevel::ERROR,
        CRITICAL = LogLevel::CRITICAL,
        ALERT = LogLevel::ALERT,
        EMERGENCY = LogLevel::EMERGENCY;

    public static $errorMap = [
        E_ERROR => LogLevel::CRITICAL,
        E_WARNING => LogLevel::WARNING,
        E_PARSE => LogLevel::ALERT,
        E_NOTICE => LogLevel::NOTICE,
        E_CORE_ERROR => LogLevel::CRITICAL,
        E_CORE_WARNING => LogLevel::WARNING,
        E_COMPILE_ERROR => LogLevel::ALERT,
        E_COMPILE_WARNING => LogLevel::WARNING,
        E_USER_ERROR => LogLevel::ERROR,
        E_USER_WARNING => LogLevel::WARNING,
        E_USER_NOTICE => LogLevel::NOTICE,
        E_STRICT => LogLevel::NOTICE,
        E_RECOVERABLE_ERROR => LogLevel::ERROR,
        E_DEPRECATED => LogLevel::NOTICE,
        E_USER_DEPRECATED => LogLevel::NOTICE,
    ];

    public static $exceptionMap = [
        \ParseError::class => LogLevel::CRITICAL,
        \Throwable::class => LogLevel::ERROR,
    ];

    /**
     * @var string[][]
     */
    private static $handlers = [
        'plain' => [
            'text/plain',
            'text/css',
            'text/javascript',
        ],
        'imagejpeg' => [
            'image/jpeg',
        ],
        'imagegif' => [
            'image/gif',
        ],
        'imagepng' => [
            'image/png',
        ],
        'svg' => [
            'image/svg+xml',
        ],
        'json' => [
            'application/json',
        ],
        'xml' => [
            'text/xml',
        ],
    ];

    /********************* misc ****************d*g**/

    /** @var int timestamp with microseconds of the start of the request */
    public static $time;

    /** @var string URI pattern mask to open editor */
    public static $editor = 'editor://open/?file=%file&line=%line';

    /** @var array replacements in path */
    public static $editorMapping = [];

    /** @var string command to open browser (use 'start ""' in Windows) */
    public static $browser;

    /** @var array */
    private static $cpuUsage;

    /********************* services ****************d*g**/

    /** @var BlueScreen */
    private static $blueScreen;

    /** @var Bar */
    private static $bar;

    /** @var LoggerInterface */
    private static $logger;

    /** @var ChromeLogger */
    private static $chromeLogger;

    /** @var Bar\TracePanel */
    private static $trace;

    public static function isProductionMode(): bool
    {
        if (self::$started) {
            return self::$productionMode;
        }

        if (self::$enabled) {
            return \is_bool(self::$mode) ? self::$mode : false;
        }

        return false;
    }

    /**
     * Enables displaying or logging errors and exceptions.
     *
     * @param  mixed  $mode         production, development mode, autodetection or IP address(es) whitelist.
     * @param  string $logDirectory error log directory
     * @param  string $email        administrator email; enables email sending in production mode
     *
     * @return void
     */
    public static function enable($mode = null, $logDirectory = null, $email = null)
    {
        if ($mode !== null || self::$productionMode === null) {
            self::$mode = $mode;

            if (\is_bool($mode)) {
                self::$productionMode = $mode;
            }
        }

        // logging configuration
        if ($email !== null) {
            self::$email = $email;
        }

        if (
            $logDirectory === null ||
            !\is_dir(self::$logDirectory) ||
            !\preg_match('#([a-z]+:)?[/\\\\]#Ai', self::$logDirectory)
        ) {
            self::exceptionHandler(new \RuntimeException('Logging directory not found or is not absolute path.'));
        }

        if ($logDirectory) {
            if (!preg_match('#([a-z]+:)?[/\\\\]#Ai', $logDirectory)) {
                self::exceptionHandler(new \RuntimeException('Logging directory must be absolute path.'));
            } elseif (!is_dir(self::$logDirectory)) {
                self::exceptionHandler(new \RuntimeException("Logging directory '" . $logDirectory . "' is not found."));
            }

            self::$logDirectory = $logDirectory;
        }

        if (self::$enabled) {
            return;
        }

        self::$reserved = \str_repeat('t', 30000);
        self::$obLevel = \ob_get_level();

        // php configuration
        if (self::$iniSet = \function_exists('\ini_set')) {
            \ini_set('html_errors', '0');
            \ini_set('log_errors', '0');
        }

        \error_reporting(E_ALL);

        \register_shutdown_function([__CLASS__, 'shutdownHandler']);
        \set_exception_handler([__CLASS__, 'exceptionHandler']);
        \set_error_handler([__CLASS__, 'errorHandler']);

        self::$enabled = true;
        self::$enabledTime = \microtime(true);
    }

    public static function start(ServerRequestInterface $request): ?ResponseInterface
    {
        if (!self::$enabled) {
            return null;
        }

        self::$request = $request;

        $modeDefined = \is_bool(self::$mode);
        if (!$modeDefined || self::$productionMode === null) {
            self::$productionMode = $modeDefined ? self::$mode : !self::detectDebugMode($request);
        }

        if (!self::$reserved) {
            self::$reserved = \str_repeat('t', 30000);
        }

        self::$time = \microtime(true);
        self::$obLevel = \ob_get_level();
        self::$cpuUsage = !self::$productionMode && \function_exists('\getrusage') ? \getrusage() : null;

        // php configuration
        if (self::$iniSet) {
            \ini_set('display_errors', self::$productionMode ? '0' : '1'); // or 'stderr'
        } elseif (
            ($displayErrors = \ini_get('display_errors')) != !self::$productionMode // intentionally ==
            && $displayErrors !== (self::$productionMode ? 'stderr' : 'stdout')
        ) {
            self::exceptionHandler(new \RuntimeException("Unable to set 'display_errors' because function ini_set() is disabled."));
        }

        if (self::$started) {
            return null;
        }
        self::$started = true;

        if (self::$productionMode) {
            return null;
        }

        if (\headers_sent($file, $line) || \ob_get_length()) {
            throw new \LogicException(
                __METHOD__ . '() called after some output has been sent. '
                . ($file ? "Output started at $file:$line." : 'Try Hail\Debugger\OutputDebugger to find where output started.')
            );
        }

        $response = self::getBar()->dispatchAssets();

        if ($response !== null) {
            self::removeOutputBuffers(false);
        }

        return $response;
    }

    /**
     * @return ServerRequestInterface
     * @throws \RuntimeException
     */
    public static function getRequest(): ServerRequestInterface
    {
        return self::$request;
    }

    /**
     * Renders loading <script>
     *
     * @return void
     */
    public static function renderLoader()
    {
        if (!self::isProductionMode()) {
            self::getBar()->renderLoader();
        }
    }

    public static function writeToResponse(ResponseInterface $response): ResponseInterface
    {
        if (self::$chromeLogger !== null) {
            self::$chromeLogger->writeToResponse($response);
        }

        self::removeOutputBuffers(false);

        if (!self::isProductionMode() &&
            (Helpers::isHtmlMode($response) || Helpers::isAjax())
        ) {
            $body = $response->getBody();
            if ($body->isWritable()) {
                \ob_start();
                self::getBar()->render();
                $content = \ob_get_clean();

                if (!$body->eof() && $body->isSeekable()) {
                    $body->rewind();
                    $body->seek($body->getSize());
                }

                $body->write($content);
            }
        }

        self::$request = null;

        return $response;
    }

    /**
     * Shutdown handler to catch fatal errors and execute of the planned activities.
     *
     * @return void
     * @internal
     */
    public static function shutdownHandler()
    {
        if (!self::$reserved) {
            return;
        }

        $error = \error_get_last();
        if (\in_array($error['type'],
            [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR, E_USER_ERROR], true
        )) {
            self::exceptionHandler(
                Helpers::fixStack(
                    new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line'])
                ), false
            );
        }
    }

    public static function getExceptionLevel(\Throwable $exception): string
    {
        foreach (self::$exceptionMap as $class => $level) {
            if ($exception instanceof $class) {
                return $level;
            }
        }

        return LogLevel::ERROR;
    }

    /**
     * Handler to catch uncaught exception.
     *
     * @param \Throwable $exception
     *
     * @return ResponseInterface
     */
    public static function exceptionToResponse(\Throwable $exception): ResponseInterface
    {
        self::$reserved = null;

        if ($exception instanceof HttpErrorException) {
            $statusCode = $exception->getCode();
        } else {
            $userAgent = self::$request->getHeaderLine('User-Agent');
            $statusCode = $userAgent && \strpos($userAgent, 'MSIE ') !== false ? 503 : 500;
        }

        $headers = [];
        $template = 'html';
        $isHtmlMode = false;

        $accept = self::$request->getHeaderLine('Accept');
        foreach (static::$handlers as $template => $types) {
            foreach ($types as $type) {
                if (\stripos($accept, $type) !== false) {
                    $headers['Content-Type'] = $type;
                    break 2;
                }
            }
        }

        if ($headers === []) {
            $isHtmlMode = Helpers::isHtmlMode();

            if ($isHtmlMode) {
                $template = '500';
                $headers['Content-Type'] = 'text/html; charset=UTF-8';
            }
        }

        Helpers::improveException($exception);
        self::removeOutputBuffers(true);

        if (self::isProductionMode()) {
            try {
                self::log($exception, self::getExceptionLevel($exception));
            } catch (\Throwable $e) {
            }

            $body = self::exceptionTemplate($template, $exception, empty($e));
        } elseif ($isHtmlMode || Helpers::isAjax()) {
            $body = self::getBlueScreen()->render($exception);
        } else {
            $level = self::getExceptionLevel($exception);
            self::chromeLog($exception, $level);

            $s = Helpers::getClass($exception) . ($exception->getMessage() === '' ? '' : ': ' . $exception->getMessage())
                . ' in ' . $exception->getFile() . ':' . $exception->getLine()
                . "\nStack trace:\n" . $exception->getTraceAsString();
            try {
                $file = self::log($exception, $level);
                if ($file) {
                    $headers['X-Tracy-Error-Log'] = $file;
                }

                $body = "$s\n" . ($file ? "(stored in $file)\n" : '');
            } catch (\Throwable $e) {
                $body = "$s\nUnable to log error: {$e->getMessage()}\n";
            }
        }

        return self::writeToResponse(
            Factory::response($statusCode, $body, $headers)
        );
    }

    protected static function exceptionTemplate(string $template, \Throwable $exception, bool $logged): string
    {
        $code = $exception->getCode();
        if ($logged) {
            $message = 'We\'re sorry! The server encountered an internal error and was unable to complete your request. Please try again later.';
        } else {
            $message = $exception->getMessage();
        }

        \ob_start();
        if (\strpos($template, 'image') === 0) {
            require __DIR__ . '/assets/Error/image.phtml';
        } else {
            require __DIR__ . '/assets/Error/' . $template . '.phtml';
        }

        return \ob_get_clean();
    }

    /**
     * Handler to catch uncaught exception.
     *
     * @param \Exception|\Throwable $exception
     * @param bool                  $exit
     *
     * @return void
     * @internal
     */
    public static function exceptionHandler($exception, $exit = true)
    {
        if (!self::$reserved && $exit) {
            return;
        }
        self::$reserved = null;

        Helpers::improveException($exception);
        self::removeOutputBuffers(true);

        if (self::isProductionMode()) {
            try {
                self::log($exception, self::getExceptionLevel($exception));
            } catch (\Throwable $e) {
            }

            $message = 'ERROR: application encountered an error and can not continue. '
                . (isset($e) ? "Unable to log error.\n" : "Error was logged.\n");

            if (PHP_SAPI === 'cli') {
                \fwrite(STDERR, $message);
            } else {
                echo $message;
            }
        } else {
            $level = self::getExceptionLevel($exception);
            self::chromeLog($exception, $level);

            $s = Helpers::getClass($exception) . ($exception->getMessage() === '' ? '' : ': ' . $exception->getMessage())
                . ' in ' . $exception->getFile() . ':' . $exception->getLine()
                . "\nStack trace:\n" . $exception->getTraceAsString();
            try {
                $file = self::log($exception, $level);

                echo "$s\n" . ($file ? "(stored in $file)\n" : '');
                if ($file && PHP_SAPI === 'cli') {
                    if (self::$browser) {
                        \exec(self::$browser . ' ' . \escapeshellarg($file));
                    } elseif (DIRECTORY_SEPARATOR === '\\') {
                        \exec(\escapeshellarg($file));
                    } elseif (strtoupper(PHP_OS) === 'DARWIN') {
                        \exec('open ' . \escapeshellarg($file));
                    }
                }
            } catch (\Throwable $e) {
                echo "$s\nUnable to log error: {$e->getMessage()}\n";
            }
        }

        if ($exit) {
            exit(255);
        }
    }

    public static function getErrorLevel($code)
    {
        return self::$errorMap[$code] ?? LogLevel::CRITICAL;
    }

    /**
     * Handler to catch warnings and notices.
     *
     * @return bool   FALSE to call normal error handler, NULL otherwise
     * @throws \ErrorException
     * @internal
     */
    public static function errorHandler($severity, $message, $file, $line, $context = [])
    {
        if (self::$scream) {
            \error_reporting(E_ALL);
        }

        $productionMode = self::isProductionMode();

        if ($severity === E_RECOVERABLE_ERROR || $severity === E_USER_ERROR) {
            $previous = null;
            if (
                isset($context['e']) &&
                ($context['e'] instanceof \Throwable) &&
                Helpers::findTrace(\debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), '*::__toString')
            ) {
                $previous = $context['e'];
            }

            $e = new \ErrorException($message, 0, $severity, $file, $line, $previous);
            $e->context = $context;
            throw $e;
        }

        if (($severity & \error_reporting()) !== $severity) {
            return false; // calls normal error handler to fill-in error_get_last()
        }

        if ($productionMode && ($severity & self::$logSeverity) === $severity) {
            $e = new \ErrorException($message, 0, $severity, $file, $line);
            $e->context = $context;
            Helpers::improveException($e);
            try {
                self::log($e, self::getErrorLevel($severity));
            } catch (\Throwable $e) {
            }

            return null;
        }

        $request = self::getRequest();

        if (!$productionMode && (!$request || !isset($request->getQueryParams()['_tracy_skip_error']))
            && (\is_bool(self::$strictMode) ? self::$strictMode : ((self::$strictMode & $severity) === $severity))
        ) {
            $e = new \ErrorException($message, 0, $severity, $file, $line);
            $e->context = $context;
            $e->skippable = true;
            self::exceptionHandler($e);
        }

        $message = 'PHP ' . Helpers::errorTypeToString($severity) . ": $message";
        $count = &self::getBar()->getPanel('Tracy:errors')->data["$file|$line|$message"];

        if ($count++) { // repeated error
            return null;
        }

        if ($productionMode) {
            try {
                self::log("$message in $file:$line", self::getErrorLevel($severity));
            } catch (\Throwable $e) {
            }

            return null;
        }

        self::chromeLog(new \ErrorException($message, 0, $severity, $file, $line), self::ERROR);


        return Helpers::isHtmlMode() || Helpers::isAjax() ? null : false; // FALSE calls normal error handler
    }


    public static function removeOutputBuffers($errorOccurred)
    {
        while (\ob_get_level() > self::$obLevel) {
            $status = \ob_get_status();
            if (\in_array($status['name'], ['ob_gzhandler', 'zlib output compression'], true)) {
                break;
            }
            $fnc = $status['chunk_size'] || !$errorOccurred ? 'ob_end_flush' : 'ob_end_clean';
            if (!@$fnc()) { // @ may be not removable
                break;
            }
        }
    }


    /********************* services ****************d*g**/


    /**
     * @return BlueScreen
     */
    public static function getBlueScreen(): BlueScreen
    {
        if (!self::$blueScreen) {
            self::$blueScreen = new BlueScreen;
            self::$blueScreen->info = [
                'PHP ' . PHP_VERSION,
                $_SERVER['SERVER_SOFTWARE'] ?? null,
                'Tracy ' . self::VERSION,
            ];
        }

        return self::$blueScreen;
    }


    /**
     * @return Bar
     */
    public static function getBar(): Bar
    {
        if (!self::$bar) {
            self::$bar = new Bar;
            self::$bar->addPanel($info = new Bar\DefaultPanel('info'), 'Tracy:info');
            $info->cpuUsage = self::$cpuUsage;
            self::$bar->addPanel(new Bar\DefaultPanel('errors'), 'Tracy:errors'); // filled by errorHandler()
            self::$bar->addPanel(new Bar\RoutePanel(), 'Hail:route');
            self::$bar->addPanel(new Bar\SessionPanel(), 'Session');
            self::$bar->addPanel(new Bar\QueryPanel(), 'Query');
            self::$bar->addPanel(new Bar\ProfilerPanel(), 'Profile');
            if (XDEBUG_EXTENSION && \ini_get('xdebug.auto_trace') !== 'on') {
                self::$bar->addPanel(static::getTrace(), 'Hail:Trace');
            }
            self::$bar->addPanel(new Bar\GitPanel(), 'Git');
            self::$bar->addPanel(new Bar\VendorPanel(), 'Vendor');
        }

        return self::$bar;
    }

    /**
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public static function getLogger(): LoggerInterface
    {
        if (!self::$logger) {
            self::$logger = new Logger(self::$logDirectory, self::$email, self::getBlueScreen());
        }

        return self::$logger;
    }

    /********************* useful tools ****************d*g**/


    /**
     * Dumps information about a variable in readable format.
     *
     * @tracySkipLocation
     *
     * @param  mixed $var    variable to dump
     * @param  bool  $return return output instead of printing it? (bypasses $productionMode)
     *
     * @return mixed  variable itself or dump
     */
    public static function dump($var, $return = false)
    {
        if ($return) {
            \ob_start();
            Dumper::dump($var, [
                Dumper::DEPTH => self::$maxDepth,
                Dumper::TRUNCATE => self::$maxLength,
            ]);

            return \ob_get_clean();
        }

        if (!self::isProductionMode()) {
            Dumper::dump($var, [
                Dumper::DEPTH => self::$maxDepth,
                Dumper::TRUNCATE => self::$maxLength,
                Dumper::LOCATION => self::$showLocation,
            ]);
        }

        return $var;
    }

    /**
     * Starts/stops stopwatch.
     *
     * @param  string $name name
     *
     * @return float   elapsed seconds
     */
    public static function timer($name = null)
    {
        static $time = [];
        $now = \microtime(true);
        $delta = isset($time[$name]) ? $now - $time[$name] : 0;
        $time[$name] = $now;

        return $delta;
    }

    /**
     * Dumps information about a variable in Tracy Debug Bar.
     *
     * @tracySkipLocation
     *
     * @param  mixed  $var     variable to dump
     * @param  string $title   optional title
     * @param  array  $options dumper options
     *
     * @return mixed  variable itself
     */
    public static function barDump($var, $title = null, array $options = null)
    {
        if (!self::isProductionMode()) {
            static $panel;
            if (!$panel) {
                self::getBar()->addPanel($panel = new Bar\DefaultPanel('dumps'), 'Tracy:dumps');
            }
            $panel->data[] = [
                'title' => $title,
                'dump' => Dumper::toHtml($var, $options + [
                        Dumper::DEPTH => self::$maxDepth,
                        Dumper::TRUNCATE => self::$maxLength,
                        Dumper::LOCATION => self::$showLocation ?: Dumper::LOCATION_CLASS | Dumper::LOCATION_SOURCE,
                    ]),
            ];
        }

        return $var;
    }


    /**
     * Logs message or exception.
     *
     * @param  string|\Exception|\Throwable
     *
     * @return mixed
     */
    public static function log($message, $priority = self::INFO)
    {
        return self::sendToLogger(self::getLogger(), $priority, $message);
    }

    protected static function sendToLogger(LoggerInterface $logger, $level, $message)
    {
        if ($message instanceof \Throwable) {
            return $logger->log($level, '{exception}', [
                'exception' => $message,
            ]);
        }

        return $logger->log($level, $message);
    }

    /**
     * Sends message to ChromeLogger console.
     *
     * @param  mixed $message message to log
     * @param string $priority
     *
     * @return bool    was successful?
     */
    public static function chromeLog($message, $priority = self::DEBUG)
    {
        if (!self::isProductionMode()) {
            return self::sendToLogger(self::getChromeLogger(), $priority, $message);
        }

        return false;
    }

    /**
     * @return ChromeLogger
     */
    public static function getChromeLogger(): ChromeLogger
    {
        if (self::$chromeLogger === null) {
            self::$chromeLogger = new ChromeLogger();
        }

        return self::$chromeLogger;
    }

    /**
     * Detects debug mode by IP address.
     *
     * @param  ServerRequestInterface $request
     *
     * @return bool
     */
    private static function detectDebugMode(ServerRequestInterface $request): bool
    {
        $cookie = $request->getCookieParams();

        $addr = $server['REMOTE_ADDR'] ?? \php_uname('n');
        $secret = isset($cookie[self::COOKIE_SECRET]) && \is_string($cookie[self::COOKIE_SECRET])
            ? $cookie[self::COOKIE_SECRET]
            : null;

        $list = \is_string(self::$mode)
            ? \array_map('\trim', \explode(',', self::$mode))
            : (array) self::$mode;


        if (
            !$request->hasHeader('X-Forwarded-For') &&
            !$request->hasHeader('Forwarded')
        ) {
            $list[] = '127.0.0.1';
            $list[] = '::1';
        }

        return \in_array($addr, $list, true) || \in_array("$secret@$addr", $list, true);
    }

    /**
     * @return Bar\TracePanel
     */
    public static function getTrace(): Bar\TracePanel
    {
        return static::$trace ?? (static::$trace = new Bar\TracePanel(
                \storage_path('xdebugTrace.xt')
            ));
    }
}