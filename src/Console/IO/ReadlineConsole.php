<?php

namespace Hail\Console\IO;

/**
 * Console utilities using readline.
 */
class ReadlineConsole extends Console
{
    /**
     * @var SttyInterface
     */
    private $stty;

    public function __construct(SttyInterface $stty)
    {
        $this->stty = $stty;
    }

    public static function isAvailable()
    {
        static $ext;
        if ($ext === null) {
            $ext = extension_loaded('readline');
        }

        return $ext;
    }

    public function readLine($prompt)
    {
        $line = readline($prompt);
        readline_add_history($line);
        return $line;
    }

    public function readPassword($prompt)
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            echo $prompt;

            return $this->readPasswordForWin();
        }

        return $this->noEcho(function () use ($prompt) {
            return readline($prompt);
        });
    }

    public function noEcho(\Closure $callback)
    {
        return $this->stty->withoutEcho($callback);
    }
}
