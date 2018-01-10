<?php

namespace Hail\Console\IO;

/**
 * Console utilities using STDIN.
 */
class StandardConsole extends Console
{
    /**
     * @var SttyInterface
     */
    private $stty;

    public function __construct(SttyInterface $stty)
    {
        $this->stty = $stty;
    }

    public function readLine($prompt)
    {
        echo $prompt;

        return $this->read();
    }

    public function readPassword($prompt)
    {
        echo $prompt;

        if ('\\' === DIRECTORY_SEPARATOR) {
            return $this->readPasswordForWin();
        }

        return $this->noEcho(
            \Closure::fromCallable([$this, 'read'])
        );
    }

    public function noEcho(\Closure $callback)
    {
        return $this->stty->withoutEcho($callback);
    }

    private function read()
    {
        return rtrim(fgets(STDIN), "\n");
    }
}
