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

/**
 * Command based class (application & subcommands inherit from this class)
 *
 * register subcommands.
 */
trait CommandTrait
{
    /**
     * @var Command[] application commands
     *
     * which is an associative array, contains command class mapping info
     *
     *     command name => command class name
     *
     * */
    protected $commands = [];
    protected $aliases = [];

    /**
     * @var CommandGroup[]
     */
    protected $commandGroups = [];

    /**
     * @var OptionResult parsed options
     */
    private $options;

    /**
     * @var OptionCollection
     */
    private $optionSpecs;

    /**
     * Parent commmand object. (the command caller)
     *
     * @var Command|Application
     */
    public $parent;

    /**
     * @var Argument[]
     */
    private $arguments;
    private $argumentNames = [];

    protected $extensions = [];

    /**
     * Command message logger.
     *
     * @var Logger
     */
    public $logger;

    /**
     * @var Prompter
     */
    private $prompter;

    /**
     * Returns one line brief for this command.
     *
     * @return string brief
     */
    public function brief()
    {
        return 'awesome brief for your app.';
    }

    /**
     * Usage string  (one-line)
     *
     * @return string usage
     */
    public function usage()
    {
        return '';
    }

    /**
     * Detailed help text
     *
     * @return string helpText
     */
    public function help()
    {
        return '';
    }


    /**
     * Method for users to define alias.
     *
     * @return string[]
     */
    public function aliases()
    {
        return [];
    }

    /**
     * Translate current class name to command name.
     *
     * @return string command name
     */
    public function name(): string
    {
        static $name = null;
        if ($name === null) {
            // Extract command name from the class name.
            $class = substr(strrchr(static::class, '\\'), 1);
            $name = CommandLoader::inverseTranslate($class);
        }

        return $name;
    }

    public function getPrompter(): Prompter
    {
        if ($this->prompter === null) {
            $this->prompter = new Prompter();
        }

        return $this->prompter;
    }

    /**
     * Add a command group and register the commands automatically
     *
     * @param string $groupName The group name
     * @param array  $commands  Command array combines indexed command names or command class assoc array.
     *
     * @return CommandGroup
     */
    public function addCommandGroup($groupName, $commands = [])
    {
        $group = new CommandGroup($groupName);
        foreach ($commands as $val) {
            $cmd = $this->addCommand($val);
            $group->addCommand($cmd);
        }
        $this->commandGroups[] = $group;

        return $group;
    }

    public function getCommandGroups()
    {
        return $this->commandGroups;
    }

    public function isApplication()
    {
        return $this instanceof Application;
    }

    /**
     * Get the main application object from parents or the object itself.
     *
     * @return Application|null
     */
    public function getApplication(): ?Application
    {
        if ($this instanceof Application) {
            return $this;
        }

        if ($p = $this->parent) {
            return $p->getApplication();
        }

        return null;
    }

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
    public function init()
    {
        CommandLoader::autoload($this);
    }

    /**
     * @param Application|Command $parent
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
    }

    public function getParent()
    {
        return $this->parent;
    }

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
    public function addCommand(string $class = null): Command
    {
        $realClass = CommandLoader::load($class);
        if ($realClass === null) {
            throw new CommandClassNotFoundException($class);
        }

        // register command to table
        $cmd = $this->createCommand($realClass);
        $this->connectCommand($cmd);

        return $cmd;
    }


    /**
     * getAllCommandPrototype() method is used for returning command prototype in string.
     *
     * Very useful when user entered command with wrong argument or format.
     *
     * @return array
     */
    public function getAllCommandPrototype()
    {
        $lines = [];

        if (method_exists($this, 'execute')) {
            $lines[] = $this->getCommandPrototype();
        }

        if ($this->hasCommands()) {
            foreach ($this->commands as $name => $subcmd) {
                $lines[] = $subcmd->getCommandPrototype();
            }
        }

        return $lines;
    }

    public function getCommandPrototype()
    {
        $out = [];

        $out[] = basename($this->getApplication()->getProgramName());

        // $out[] = $this->name();
        foreach ($this->getCommandNameTraceArray() as $n) {
            $out[] = $n;
        }

        if (!empty($this->getOptionCollection()->options)) {
            $out[] = '[options]';
        }
        if ($this->hasCommands()) {
            $out[] = '<subcommand>';
        } else {
            foreach ($this->getArguments() as $argument) {
                $out[] = '<' . $argument->name() . '>';
            }
        }

        return implode(' ', $out);
    }


    /**
     * connectCommand connects a command name with a command object.
     *
     * @param Command $cmd
     */
    public function connectCommand(Command $cmd)
    {
        $name = $cmd->name();
        $this->commands[$name] = $cmd;

        // regsiter command aliases to the alias table.
        $aliases = $cmd->aliases();
        if (is_string($aliases)) {
            $aliases = preg_split('/\s+/', $aliases);
        }

        if (!is_array($aliases)) {
            throw new InvalidArgumentException('Aliases needs to be an array or a space-separated string.');
        }

        foreach ($aliases as $alias) {
            $this->aliases[$alias] = $cmd;
        }
    }


    /**
     * Aggregate command info
     */
    public function aggregate()
    {
        $commands = [];
        foreach ($this->getVisibleCommands() as $name => $cmd) {
            $commands[$name] = $cmd;
        }

        foreach ($this->commandGroups as $g) {
            if ($g->isHidden) {
                continue;
            }
            foreach ($g->getCommands() as $name => $cmd) {
                unset($commands[$name]);
            }
        }

        uasort($this->commandGroups, function ($a, $b) {
            if ($a->getId() === 'dev') {
                return 1;
            }

            return 0;
        });

        return [
            'groups' => $this->commandGroups,
            'commands' => $commands,
        ];
    }


    /**
     * Return true if this command has subcommands.
     *
     * @return bool
     */
    public function hasCommands(): bool
    {
        return !empty($this->commands);
    }

    /**
     * Check if a command name is registered in this application / command object.
     *
     * @param string $command command name
     *
     * @return bool
     */
    public function hasCommand(string $command): bool
    {
        return isset($this->commands[$command]) || isset($this->aliases[$command]);
    }

    /**
     * Get command name list
     *
     * @return array command name list
     */
    public function getCommandList()
    {
        return array_keys($this->commands);
    }


    /**
     * Some commands are not visible. when user runs 'help', we should just
     * show them these visible commands
     *
     * @return string[] CommandBase command map
     */
    public function getVisibleCommands(): array
    {
        $commands = [];
        foreach ($this->commands as $name => $command) {
            if ($name[0] === '_') {
                continue;
            }

            $commands[$name] = $command;
        }

        return $commands;
    }


    /**
     * Command names start with understore are hidden command. we ignore the
     * commands.
     *
     * @return string[]
     */
    public function getVisibleCommandList(): array
    {
        return array_keys($this->getVisibleCommands());
    }


    /**
     * Return the command name stack
     *
     * @return string[]
     */
    public function getCommandNameTraceArray()
    {
        $cmdStacks = [$this->name()];
        $p = $this->parent;
        while ($p) {
            if (!$p instanceof Application) {
                $cmdStacks[] = $p->name();
            }
            $p = $p->parent;
        }

        return array_reverse($cmdStacks);
    }

    public function getSignature()
    {
        return implode('.', $this->getCommandNameTraceArray());
    }


    /**
     * Return the objects of all sub commands.
     *
     * @return Command[]
     */
    public function getCommands()
    {
        return $this->commands;
    }

    /**
     * Get subcommand object from current command
     * by command name.
     *
     * @param string $command
     *
     * @return Command initialized command object.
     * @throws CommandNotFoundException
     */
    public function getCommand($command): Command
    {
        if (isset($this->aliases[$command])) {
            return $this->aliases[$command];
        }

        if (isset($this->commands[$command])) {
            return $this->commands[$command];
        }

        throw new CommandNotFoundException($this, $command);
    }

    public function guessCommand($commandName)
    {
        // array of words to check against
        $words = array_keys($this->commands);

        return Corrector::correct($commandName, $words);
    }


    /**
     * Create and initialize command object.
     *
     * @param  string $class Command class.
     *
     * @return Command command object.
     */
    public function createCommand(string $class): Command
    {
        /** @var Command $cmd */
        $cmd = new $class($this);
        $cmd->init();

        return $cmd;
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;

        return $this;
    }

    public function getOutput(): Logger
    {
        return $this->logger;
    }

    /**
     * @param string|Option $spec
     * @param string|null   $desc
     * @param string|null   $key
     *
     * @return Option
     */
    public function addOption($spec, string $desc = null, string $key = null): Option
    {
        $option = $this->getOptionCollection();

        return $option->add($spec, $desc, $key);
    }

    public function getOption(string $key)
    {
        return ($this->options && $this->options->has($key)) ? $this->options->get($key) : null;
    }

    /**
     * Get Option Results
     *
     * @return OptionResult command options object (parsed, and a option results)
     */
    public function getOptions(): OptionResult
    {
        return $this->options;
    }

    /**
     * Set option results
     *
     * @param OptionResult $options
     */
    public function setOptions(OptionResult $options)
    {
        $this->options = $options;
    }

    /**
     * Get Command-line Option spec
     *
     * @return OptionCollection
     */
    public function getOptionCollection(): OptionCollection
    {
        // get option parser, init specs from the command.
        if (!$this->optionSpecs) {
            $this->optionSpecs = new OptionCollection;
        }

        return $this->optionSpecs;
    }

    /**
     * Prepare stage method
     */
    public function prepare()
    {
        foreach ($this->extensions as $extension) {
            $extension->prepare();
        }
    }

    /**
     * Finalize stage method
     */
    public function finish()
    {
        foreach ($this->extensions as $extension) {
            $extension->finish();
        }
    }

    public function addArgument(string $name, string $desc = null): Argument
    {
        if ($this->arguments === null) {
            $this->arguments = [];
        }

        $argument = new Argument($name, $desc);
        $this->arguments[] = $argument;
        $this->argumentNames[$argument->name] = $argument;

        return $argument;
    }

    /**
     * @param int|string $key
     *
     * @return Argument|null
     */
    public function findArgument($key): ?Argument
    {
        $arguments = $this->getArguments();

        if (isset($this->argumentNames[$key])) {
            return $this->argumentNames[$key];
        }

        return $arguments[$key] ?? null;
    }

    /**
     * @param int|string $key
     *
     * @return mixed|null
     */
    public function getArgument($key)
    {
        $argument = $this->findArgument($key);
        if ($argument === null) {
            return null;
        }

        return $argument->getValue();
    }

    /**
     * Return the defined argument info objects.
     *
     * @return Argument[]
     * @throws ExecuteMethodNotDefinedException
     */
    public function getArguments(): array
    {
        // if user not define any arguments, get argument info from method parameters
        if ($this->arguments === null) {
            $this->arguments = [];

            $ro = new \ReflectionObject($this);
            if (!$ro->hasMethod('execute')) {
                throw new ExecuteMethodNotDefinedException($this);
            }

            $method = $ro->getMethod('execute');
            $parameters = $method->getParameters();

            foreach ($parameters as $param) {
                $a = $this->addArgument($param->getName());
                if ($param->isOptional()) {
                    $a->optional();

                    if ($param->isDefaultValueAvailable()) {
                        $a->setValue($param->getDefaultValue());
                    }
                }
            }
        }

        return $this->arguments;
    }

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
    public function executeWrapper(array $args)
    {
        if (!method_exists($this, 'execute')) {
            $cmd = $this->createCommand(Help::class);

            $cmd->executeWrapper([$this->name()]);

            return;
        }

        // Validating arguments
        foreach ($this->getArguments() as $k => $argument) {
            if (!isset($args[$k])) {
                if ($argument->isRequired()) {
                    throw new RequireValueException("Argument pos {$k} '{$argument->name()}' requires a value.");
                }

                continue;
            }

            if (!$argument->validate($args[$k])) {
                $this->logger->error("Invalid argument {$args[$k]}");

                return;
            }

            $args[$k] = $argument->getValue();
        }

        $refMethod = new \ReflectionMethod($this, 'execute');
        $requiredNumber = $refMethod->getNumberOfRequiredParameters();

        $count = count($args);
        if ($count < $requiredNumber) {
            throw new CommandArgumentNotEnoughException($this, $count, $requiredNumber);
        }

        foreach ($this->extensions as $extension) {
            $extension->execute();
        }

        $this->execute(...$args);
    }
}
