<?php
/*
 * This file is part of the CLIFramework package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console;

use Hail\Util\SingletonTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

class Logger implements LoggerInterface
{
    use LoggerTrait;
    use SingletonTrait;

    protected $logLevels = [
        LogLevel::EMERGENCY => 8,
        LogLevel::ALERT => 7,
        LogLevel::CRITICAL => 6,
        LogLevel::ERROR => 5,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 3,
        LogLevel::INFO => 2,
        LogLevel::DEBUG => 1,
    ];

    public $levelStyles = [
        LogLevel::EMERGENCY => 'strong_red',
        LogLevel::ALERT => 'strong_red',
        LogLevel::CRITICAL => 'strong_red',
        LogLevel::ERROR => 'red',
        LogLevel::WARNING => 'yellow',
        LogLevel::NOTICE => 'green',
        LogLevel::INFO => 'green',
        LogLevel::DEBUG => 'white',
    ];

    /**
     * current level
     *
     * any message level greater than or equal to this will be displayed.
     * */
    public $level = 3;

    protected $indent = 0;

    protected $indentCharacter = '  ';

    /**
     * foramtter class
     *
     * @var Formatter
     */
    public $formatter;

    /**
     * @var resource
     */
    private $stream;

    public function init()
    {
        $this->formatter = Formatter::getInstance();
        $this->stream = \fopen('php://output', 'wb');
    }

    public function __destruct()
    {
        \fclose($this->stream);
    }

    public function setLevel(?string $level)
    {
        if ($level === null) {
            $this->level = 9;
        }

        if (isset($this->logLevels[$level])) {
            $this->level = $this->logLevels[$level];
        }
    }

    public function getLevel($level = null)
    {
        if ($level === null) {
            return $this->level;
        }

        return $this->logLevels[$level] ?? null;
    }

    public function setStream(resource $stream)
    {
        if ($this->stream !== null) {
            \fclose($this->stream);
        }

        $this->stream = $stream;
    }

    public function indent($level = 1)
    {
        $this->indent += $level;
    }

    public function unIndent($level = 1)
    {
        $this->indent = \max(0, $this->indent - $level);
    }

    public function resetIndent()
    {
        $this->indent = 0;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed        $level
     * @param string|array $message
     * @param array        $context
     *
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        if (!isset($this->logLevels[$level])) {
            $level = LogLevel::DEBUG;
        }

        if ($this->logLevels[$level] < $this->level) {
            // do not print.
            return;
        }

        if ($this->level === 3 && $this->logLevels[$level] === 3) {
            $style = 'dim';
        } else {
            $style = $this->levelStyles[$level];
        }

        if ($this->indent) {
            $this->write(\str_repeat($this->indentCharacter, $this->indent));
        }

        if (\is_object($message) || \is_array($message)) {
            $this->writeln(\print_r($message, true), $style);
        } else {
            $this->writeln($message, $style);
        }
    }

    /**
     * @param string $text text to write by `writer`
     * @param string $style
     */
    public function write($text, $style = null)
    {
        if ($style !== null) {
            $text = $this->formatter->format($text, $style);
        } else {
            $text = $this->format($text);
        }

        \fwrite($this->stream, $text);
    }

    /**
     * @param string $text write text and append a newline charactor.
     * @param string $style
     */
    public function writeln($text, $style = null)
    {
        if ($style !== null) {
            $text = $this->formatter->format($text, $style);
        } else {
            $text = $this->format($text);
        }

        \fwrite($this->stream, $text . "\n");
    }

    /**
     * Append a newline charactor to the console
     */
    public function newline()
    {
        \fwrite($this->stream, "\n");
    }

    /**
     * @param \Exception $exception an exception to write to console.
     */
    public function logException(\Exception $exception)
    {
        echo $exception->getMessage();
        $this->newline();
    }

    public function format(string $text): string
    {
        return \preg_replace_callback('#<(\w+)>(.*?)</\1>#i', function ($matches) {
            [$raw, $style, $text] = $matches;

            switch ($style) {
                case 'b':
                    $style = 'bold';
                    break;
                case 'u':
                    $style = 'underline';
                    break;
            }

            if ($this->formatter->hasStyle($style)) {
                return $this->formatter->format($text, $style);
            }

            return $raw;
        }, $text);
    }
}
