<?php

namespace Hail\Console\Command;

use Hail\Util\Arrays;
use Hail\Console\Command;
use Hail\Console\Option\OptionResult;
use Hail\Console\Exception\{
    UnsupportedShellException,
    UndefinedArgumentException,
    UndefinedOptionException
};
use InvalidArgumentException;

class Meta extends Command
{
    public function brief()
    {
        return 'Return the meta data of a commands.';
    }

    public function init()
    {
        $this->addOption('flat', 'flat list format. work for both zsh and bash.');
        $this->addOption('zsh', 'output for zsh');
        $this->addOption('bash', 'output for bash');
        $this->addOption('json', 'output in JSON format (un-implemented)');
    }

    /**
     * Enable a way to get meta information of argument or option from a command.
     *
     *     app meta sub1.sub2.sub3 arg 1 valid-values
     *     app meta sub1.sub2.sub3 arg 1 suggestions
     *     app meta sub1.sub2.sub3 opt email valid-values
     */
    public function execute($commandlist, $type, $arg = null, $attr = null)
    {
        $commandNames = \explode('.', $commandlist);
        // lookup commands
        $app = $this->getApplication();
        $cmd = $app;

        if ($commandNames[0] === 'app') {
            unset($commandNames[0]);
        }

        $this->logger->debug('Finding command ' . \get_class($cmd));

        foreach ($commandNames as $commandName) {
            if (!$cmd->hasCommands()) {
                break;
            }

            $this->logger->debug('Finding command ' . $commandName);
            $cmd = $cmd->getCommand($commandName);
            $this->logger->debug('Found command class ' . \get_class($cmd));
        }

        // 'arg' or 'opt' require the argument name and attribute type
        if ($attr === null || (\in_array($type, ['arg', 'opt'], true) && $arg === null)) {
            throw new InvalidArgumentException("'arg' or 'opt' require the attribute type.");
        }

        try {
            if (!$cmd) {
                throw new \RuntimeException('Can not find command.');
            }

            switch ($type) {
                case 'arg':
                    $idx = (int) $arg;

                    if (($argument = $cmd->findArgument($idx)) === null) {
                        throw new UndefinedArgumentException("Undefined argument at $idx");
                    }

                    switch ($attr) {
                        case 'suggestions':
                            if ($values = $argument->getSuggestions()) {
                                $this->outputValues($values, $this->getOptions());

                                return;
                            }
                            break;

                        case 'valid-values':
                            if ($values = $argument->getValidValues()) {
                                $this->outputValues($values, $this->getOptions());

                                return;
                            }
                            break;
                    }
                    break;
                case 'opts':
                    $options = $cmd->getOptionCollection();
                    $values = [];
                    foreach ($options as $opt) {
                        if ($opt->short) {
                            $values[] = '-' . $opt->short;
                        } elseif ($opt->long) {
                            $values[] = '--' . $opt->long;
                        }
                    }
                    echo \implode(' ', $values), "\n";

                    return;
                case 'opt':
                    $options = $cmd->getOptionCollection();
                    $option = $options->find($arg);
                    if (!$option) {
                        throw new UndefinedOptionException("Option '$arg' not found", $cmd, $options);
                    }
                    switch ($attr) {
                        case 'isa':
                            echo $option->isa;

                            return;

                        case 'valid-values':
                            if ($values = $option->getValidValues()) {
                                $this->outputValues($values, $this->getOptions());

                                return;
                            }
                            break;
                        case 'suggestions':
                            if ($values = $option->getSuggestions()) {
                                $this->outputValues($values, $this->getOptions());

                                return;
                            }
                            break;
                    }
                    break;
                default:
                    throw new \RuntimeException("Invalid type '$type', valid types are 'arg', 'opt', 'opts'");
            }
        } catch (UnsupportedShellException $e) {
            \fwrite(STDERR, $e->getMessage() . "\n");
            \fwrite(STDERR, "Supported shells: zsh, bash\n");
        } catch (UndefinedOptionException $e) {
            \fwrite(STDERR, $e->command->getSignature() . "\n");
            \fwrite(STDERR, $e->getMessage() . "\n");
            \fwrite(STDERR, "Valid options:\n");
            foreach ($e->getOptions() as $opt) {
                if ($opt->short && $opt->long) {
                    \fwrite(STDERR, ' ' . $opt->short . '|' . $opt->long);
                } elseif ($opt->short) {
                    \fwrite(STDERR, ' ' . $opt->short);
                } elseif ($opt->long) {
                    \fwrite(STDERR, ' ' . $opt->long);
                }
                \fwrite(STDERR, "\n");
            }
        }
    }

    public function outputValues($values, OptionResult $opts)
    {
        // indexed array
        if (\is_array($values) && empty($values)) {
            return;
        }

        // for assoc array in indexed array
        if (\is_array($values) && !Arrays::isAssoc($values)) {
            if (\is_array(\end($values))) {
                $this->logger->writeln('#descriptions');
                if ($opts->zsh) {
                    // for zsh, we output the first line as the label
                    foreach ($values as [$key, $val]) {
                        $this->logger->writeln("$key:" . \addcslashes($val, ':'));
                    }
                } else {
                    foreach ($values as $value) {
                        $this->logger->writeln($value[0]);
                    }
                }
            } else { // indexed array is a list.
                $this->logger->writeln('#values');
                $this->logger->writeln(\implode("\n", $values));
            }
        } else { // associative array
            $this->logger->writeln('#descriptions');
            if ($opts->zsh) {
                foreach ($values as $key => $desc) {
                    $this->logger->writeln("$key:" . \addcslashes($desc, ':'));
                }
            } else {
                foreach ($values as $key => $desc) {
                    $this->logger->writeln($key);
                }
            }
        }
    }

    protected static function encodeString($str)
    {
        return '"' . \addcslashes($str, '"') . '"';
    }

    /**
     * currenty it supports zsh format encode "label:desc".
     */
    protected static function encodeArray($array)
    {
        if (Arrays::isAssoc($array)) {
            $output = [];
            foreach ($array as $key => $val) {
                $output[] = $key . ':' . \addcslashes($val, ': ');
            }

            return self::encodeString(\implode(' ', $output));
        }

        $output = [];
        foreach ($array as $val) {
            $output[] = \addcslashes($val, ': ');
        }

        return self::encodeString(\implode(' ', $output));
    }
}
