<?php

namespace Hail\Util;

use Hail\Util\Exception\ShellException;

class Shell
{
    /**
     * @var bool
     */
    private $displayStderr = false;

    private $output = '';
    private $prepend;
    private $stdin;

    public function __construct(array $prepend = [])
    {
        $this->prepend = $prepend;
    }

    /**
     * Output stderr to standard output
     *
     * @return Shell
     */
    public function error(): self
    {
        $this->displayStderr = true;

        return $this;
    }

    public function __toString()
    {
        return $this->output;
    }

    /**
     * @param array $arguments
     *
     * @throws ShellException
     */
    private function run(array $arguments)
    {
        // Unwind the args, figure out which ones were passed in as an array
        $this->stdin = null;
        $closureOut = false;
        foreach ($arguments as $k => $argument) {
            // If it's being passed in as an object, then pipe into stdin
            if (\is_object($argument)) {
                // If it's a anonymous function, then push stdout into it
                if ($argument instanceof \Closure) {
                    $closureOut = $argument;
                } else {
                    $this->stdin = (string) $argument;
                }
                unset($arguments[$k]);
            } elseif (\is_array($argument)) {
                if (Arrays::isAssoc($argument)) {
                    // Ok, so we're passing in arguments
                    $output = '';
                    foreach ($argument as $key => $val) {
                        if ($output !== '') {
                            $output .= ' ';
                        }
                        // If you pass 'false', it'll ignore the arg altogether
                        if ($val !== false) {
                            // Figure out if it's a long or short commandline arg
                            if (\strlen($key) === 1) {
                                $output .= '-';
                            } else {
                                $output .= '--';
                            }
                            $output .= $key;
                            // If you just pass in 'true', it'll just add the arg
                            if ($val !== true) {
                                $output .= ' ' . \escapeshellarg($val);
                            }
                        }
                    }
                    $arguments[$k] = $output;
                } else {
                    // We're passing in an array, but it's not --key=val style
                    $arguments[$k] = \implode(' ', $argument);
                }
            }
        }
        $shell = \implode(' ', $arguments);

        // Prepend the path
        if (\stripos(PHP_OS, 'win') !== 0) {
            $parts = \explode(' ', $shell);
            $parts[0] = \exec('which ' . $parts[0]);
            if ($parts[0] !== '') {
                $shell = \implode(' ', $parts);
            }
        }

        $descriptor_spec = [
            0 => ['pipe', 'r'], // Stdin
            1 => ['pipe', 'w'], // Stdout
            2 => ['pipe', 'w'] // Stderr
        ];
        $process = \proc_open($shell, $descriptor_spec, $pipes);
        if (\is_resource($process)) {
            \fwrite($pipes[0], $this->stdin);
            \fclose($pipes[0]);
            $output = '';
            while (!\feof($pipes[1])) {
                $stdout = \fgets($pipes[1], 1024);
                if ($stdout === '') {
                    break;
                }

                echo $stdout;

                if ($closureOut instanceof \Closure) {
                    $closureOut($stdout);
                }
                $output .= $stdout;
            }
            $error_output = \trim(\stream_get_contents($pipes[2]));
            if ($this->displayStderr) {
                echo $error_output;
            }
            $this->output = $output;
            \fclose($pipes[1]);
            \fclose($pipes[2]);
            $return_value = \proc_close($process);
            if ($return_value !== 0) {
                throw new ShellException($error_output, $return_value);
            }
        } else {
            throw new ShellException('Process failed to spawn');
        }
    }

    /**
     * @param array ...$args
     *
     * @return $this
     * @throws ShellException
     */
    public function __invoke(...$args)
    {
        $this->run($args);

        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     *
     * @return $this
     * @throws ShellException
     */
    public function __call($name, $arguments)
    {
        \array_unshift($arguments, $name);
        if ([] !== $this->prepend) {
            $arguments = \array_merge($this->prepend, $arguments);
        }
        $this->run($arguments);

        return $this;
    }
}