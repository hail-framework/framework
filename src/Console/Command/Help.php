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

namespace Hail\Console\Command;

use Hail\Console\Argument;
use Hail\Console\Command;
use Hail\Console\CommandInterface;
use Hail\Console\Option\OptionCollection;
use Hail\Console\Option\OptionPrinter;
use Hail\Console\Corrector;

class Help extends Command
{
    /**
     * one line description.
     */
    public function brief()
    {
        return 'Show help message of a command';
    }

    public function init()
    {
        $this->addOption('dev', 'Show development commands');
    }

    public function calculateColumnWidth($words, $min = 0)
    {
        $maxWidth = $min;
        foreach ($words as $word) {
            if (strlen($word) > $maxWidth) {
                $maxWidth = strlen($word);
            }
        }

        return $maxWidth;
    }

    public function layoutCommands($commands, $indent = 4)
    {
        $cmdNames = array_filter(array_keys($commands), function ($n) {
            return strpos($n, '_') !== 0;
        });
        $maxWidth = $this->calculateColumnWidth($cmdNames, 12);
        foreach ($commands as $name => $class) {
            $cmd = new $class();
            $brief = $cmd->brief();
            $this->logger->writeln(str_repeat(' ', $indent)
                . sprintf('%' . ($maxWidth + $indent) . 's    %s',
                    $name,
                    $brief
                ));
        }
        $this->logger->newline();
    }

    /**
     * Show command help message.
     *
     * @param array ...$commandNames command name
     *
     * @throws \Exception
     */
    public function execute(...$commandNames)
    {
        $logger = $this->logger;
        $app = $this->getApplication();
        $progname = basename($app->getProgramName());

        // if there is no subcommand to render help, show all available commands.
        $count = count($commandNames);

        if ($count) {
            $cmd = $app;
            for ($i = 0; $cmd && $i < $count; ++$i) {
                $cmd = $cmd->getCommand($commandNames[$i]);
            }

            if (!$cmd) {
                throw new \Exception('Command entry ' . implode(' ', $commandNames) . ' not found');
            }

            if ($brief = $cmd->brief()) {
                $logger->writeln('NAME', 'yellow');
                $logger->writeln("\t<strong_white>" . $cmd->name() . '</strong_white> - ' . $brief);
                $logger->newline();
            }

            if ($aliases = $cmd->aliases()) {
                $logger->writeln('ALIASES', 'yellow');
                $logger->writeln("\t<strong_white>" . implode(', ', $aliases) . '</strong_white>');
                $logger->newline();
            }

            $this->printUsage($cmd);

            $logger->writeln('SYNOPSIS', 'yellow');
            $prototypes = $cmd->getAllCommandPrototype();
            foreach ($prototypes as $prototype) {
                $logger->writeln("\t" . ' ' . $prototype);
            }
            $logger->newline();

            $this->printOptions($cmd);
            $this->printCommand($cmd);
            $this->printHelp($cmd);
        } else {
            // print application
            $cmd = $this->parent;
            $logger->writeln(ucfirst($cmd->brief()), 'strong_white');
            $logger->newline();

            $this->printUsage($cmd);

            $logger->writeln('SYNOPSIS', 'yellow');
            $logger->write("\t" . $progname);
            if (!empty($cmd->getOptionCollection()->options)) {
                $logger->write(' [options]');
            }

            if ($cmd->hasCommands()) {
                $logger->write(' <command>');
            } else {
                foreach ($cmd->getArguments() as $argument) {
                    $logger->write(' <' . $argument->name() . '>');
                }
            }

            $logger->newline();
            $logger->newline();

            $this->printOptions($cmd);
            $this->printCommand($cmd);
            $this->printHelp($cmd);
        }
    }

    protected function printUsage(CommandInterface $cmd)
    {
        if ($usage = trim($cmd->usage())) {
            $logger = $this->getOutput();

            $logger->writeln('USAGE', 'yellow');
            $logger->writeln("\t" . $usage);
            $logger->newline();
        }
    }

    protected function printOptions(CommandInterface $cmd)
    {
        $printer = OptionPrinter::getInstance();

        if ($optionLines = $printer->render($cmd->getOptionCollection())) {
            $logger = $this->getOutput();

            $logger->writeln('OPTIONS', 'yellow');
            $logger->writeln($optionLines);
        }
    }

    protected function printCommand(CommandInterface $cmd)
    {
        $logger = $this->getOutput();

        $ret = $cmd->aggregate();

        if (!empty($ret['commands'])) {
            $logger->writeln('COMMANDS', 'yellow');
            $this->layoutCommands($ret['commands']);
        }

        // show "General commands" title if there are more than one groups
        if ($this->getOption('dev') || count($ret['groups']) > 1) {
            $logger->writeln('  <strong_white>General Commands</strong_white>');

            foreach ($ret['groups'] as $group) {
                if (!$this->getOption('dev') && $group->getId() === 'dev') {
                    continue;
                }
                $logger->writeln('  <strong_white>' . $group->getName() . '</strong_white>');
                $this->layoutCommands($group->getCommands());
            }
        }
    }

    protected function printHelp(CommandInterface $cmd)
    {
        if ($help = $cmd->help()) {
            $logger = $this->getOutput();
            $logger->writeln('HELP', 'yellow');
            $logger->writeln(
                "\t" . implode("\n\t", explode("\n", $help))
            );
        }
    }
}
