<?php
/*
 * This file is part of the CLIFramework package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Console;

use Hail\Console\Exception\RequireValueException;
use InvalidArgumentException;
use Hail\Console\Option\Option;
use Hail\Console\Option\OptionCollection;
use Hail\Console\Option\OptionResult;
use Hail\Console\Component\Prompter;
use Hail\Console\Exception\CommandNotFoundException;
use Hail\Console\Exception\CommandArgumentNotEnoughException;
use Hail\Console\Exception\CommandClassNotFoundException;
use Hail\Console\Exception\ExecuteMethodNotDefinedException;

use Hail\Console\Command\Help;

interface CommandInterface
{

    /**
     * Returns one line brief for this command.
     *
     * @return string brief
     */
    public function brief();

    /**
     * Usage string  (one-line)
     *
     * @return string usage
     */
    public function usage();

    /**
     * Detailed help text
     *
     * @return string helpText
     */
    public function help();


    /**
     * Method for users to define alias.
     *
     * @return string[]
     */
    public function aliases();

    /**
     * Translate current class name to command name.
     *
     * @return string command name
     */
    public function name(): string;

    public function getPrompter(): Prompter;

    /**
     * Add a command group and register the commands automatically
     *
     * @param string $groupName The group name
     * @param array  $commands  Command array combines indexed command names or command class assoc array.
     *
     * @return CommandGroup
     */
    public function addCommandGroup($groupName, $commands = []);

    public function getCommandGroups();

    public function isApplication();

    /**
     * Get the main application object from parents or the object itself.
     *
     * @return Application|null
     */
    public function getApplication(): ?Application;

    /**
     * Users register sub-command / options /argument here.
     *
     * @code
     *
     *      function init() {
     *          // parent::init();
     *          $this->addCommand(Help::class);
     *
     *          $this->addOption('v|verbose','Verbose messages');
     *          $this->addOption('d|debug',  'Debug messages');
     *          $this->addOption('level:',  'Level takes a value.');
     *
     *          $this->addArgument('verbose','Verbose messages');
     *          $this->addArgument('debug',  'Debug messages');
     *      }
     */
    public function init();

    /**
     * @param Application|Command $parent
     */
    public function setParent($parent);

    public function getParent();

    /**
     * Register a command to application, in init() method stage,
     * we save command classes in property `commands`.
     *
     * When command is needed, get the command from property `commands`, and
     * initialize the command object.
     *
     * class name could be full-qualified or subclass name (under App\Command\ )
     *
     * @param  string $class Full-qualified Class name
     *
     * @return Command Loaded class name
     * @throws CommandClassNotFoundException
     */
    public function addCommand(string $class = null): Command;


    /**
     * getAllCommandPrototype() method is used for returning command prototype in string.
     *
     * Very useful when user entered command with wrong argument or format.
     *
     * @return array
     */
    public function getAllCommandPrototype();

    public function getCommandPrototype();


    /**
     * connectCommand connects a command name with a command object.
     *
     * @param Command $cmd
     */
    public function connectCommand(Command $cmd);


    /**
     * Aggregate command info
     */
    public function aggregate();


    /**
     * Return true if this command has subcommands.
     *
     * @return bool
     */
    public function hasCommands(): bool;

    /**
     * Check if a command name is registered in this application / command object.
     *
     * @param string $command command name
     *
     * @return bool
     */
    public function hasCommand(string $command): bool;

    /**
     * Get command name list
     *
     * @return array command name list
     */
    public function getCommandList();


    /**
     * Some commands are not visible. when user runs 'help', we should just
     * show them these visible commands
     *
     * @return string[] CommandBase command map
     */
    public function getVisibleCommands(): array;


    /**
     * Command names start with understore are hidden command. we ignore the
     * commands.
     *
     * @return string[]
     */
    public function getVisibleCommandList(): array;


    /**
     * Return the command name stack
     *
     * @return string[]
     */
    public function getCommandNameTraceArray();

    public function getSignature();


    /**
     * Return the objects of all sub commands.
     *
     * @return Command[]
     */
    public function getCommands();

    /**
     * Get subcommand object from current command
     * by command name.
     *
     * @param string $command
     *
     * @return Command initialized command object.
     * @throws CommandNotFoundException
     */
    public function getCommand($command): Command;

    public function guessCommand($commandName);


    /**
     * Create and initialize command object.
     *
     * @param  string $class Command class.
     *
     * @return Command command object.
     */
    public function createCommand(string $class): Command;

    public function setLogger($logger);
    public function getOutput(): Logger;

    /**
     * @param string|Option $spec
     * @param string|null   $desc
     * @param string|null   $key
     *
     * @return Option
     */
    public function addOption($spec, string $desc = null, string $key = null): Option;

    public function getOption(string $key);

    /**
     * Get Option Results
     *
     * @return OptionResult command options object (parsed, and a option results)
     */
    public function getOptions(): OptionResult;

    /**
     * Set option results
     *
     * @param OptionResult $options
     */
    public function setOptions(OptionResult $options);

    /**
     * Get Command-line Option spec
     *
     * @return OptionCollection
     */
    public function getOptionCollection(): OptionCollection;

    /**
     * Prepare stage method
     */
    public function prepare();

    /**
     * Finalize stage method
     */
    public function finish();

    public function addArgument(string $name, string $desc = null): Argument;

    /**
     * @param int|string $key
     *
     * @return Argument|null
     */
    public function findArgument($key): ?Argument;

    /**
     * @param int|string $key
     *
     * @return mixed|null
     */
    public function getArgument($key);

    /**
     * Return the defined argument info objects.
     *
     * @return Argument[]
     * @throws ExecuteMethodNotDefinedException
     */
    public function getArguments(): array;

    /**
     * Execute command object, this is a wrapper method for execution.
     *
     * In this method, we check the command arguments by the Reflection feature
     * provided by PHP.
     *
     * @param  array $args command argument list (not associative array).
     *
     * @throws CommandArgumentNotEnoughException
     * @throws RequireValueException
     * @throws \ReflectionException
     */
    public function executeWrapper(array $args);
}
