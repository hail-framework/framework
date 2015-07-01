<?php
/**
* Created by IntelliJ IDEA.
* User: Hao
* Date: 2015/7/1 0001
* Time: 16:22
*/

namespace Hail\Cli;


/**
 * Class SubCommand
 * Simply overwrites certain features to allow multiple command definitions, while still keeping with the main Command API
 * @package Commando
 */
class SubCommand extends Command {
    /**
     * @var string The subcommands name
     */
    protected $subname;

    /**
     * @param $tokens
     * @param $command
     */
    public function __construct($tokens, $command)
    {
        parent::__construct($tokens);
        $this->subname = $command;
    }

    /**
     * // the main parser should handle this, lets just clean up our tokens first ..
     * @return null
     */
    public function parse()
    {
        // verify we are supposed to be running now..
        if($this->isParsed() === false) {
            $tokens = $this->getTokens();
            // are we the correct sub?
            if(isset($tokens[1]) === true
                && $this->name() === $tokens[1]) {
                // ... remove our subcommand from list of parsees.
                unset($tokens[1]);
                $this->setTokens(array_values($tokens));
                return parent::parse();
            }
        }
        return null;
    }

    /**
     * @return string Subcommand name
     */
    public function name()
    {
        return $this->subname;
    }

    /**
     *
     */
    public function __destruct() {} // nada ta do
}