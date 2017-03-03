<?php
/*
 * This file is part of the GetOptionKit package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console;

use Hail\Console\Exception\InvalidOptionException;
use Hail\Console\Exception\RequireValueException;

class OptionParser
{
	public $specs;
	public $longOptions;
	public $shortOptions;

	public function __construct(OptionCollection $specs)
	{
		$this->specs = $specs;
	}

	public function setSpecs(OptionCollection $specs)
	{
		$this->specs = $specs;
	}

	/**
	 * consume option value from current argument or from the next argument
	 *
	 * @param Option   $spec
	 * @param Argument $arg
	 * @param Argument $next
	 *
	 * @return int next token consumed?
	 * @throws RequireValueException
	 */
	protected function consumeOptionToken(Option $spec, Argument $arg, Argument $next)
	{
		// Check options doesn't require next token before
		// all options that require values.
		if ($spec->isFlag()) {
			if ($spec->isIncremental()) {
				$spec->increaseValue();
			} else {
				$spec->setValue(true);
			}
		} elseif ($spec->isRequired()) {
			if ($next && !$next->isEmpty() && !$next->anyOfOptions($this->specs)) {
				$spec->setValue($next->arg);

				return 1;
			} else {
				throw new RequireValueException("Option '{$arg->getOptionName()}' requires a value.");
			}
		} else if ($spec->isMultiple()) {
			if ($next && !$next->isEmpty() && !$next->anyOfOptions($this->specs)) {
				$spec->pushValue($next->arg);

				return 1;
			}
		} else if ($spec->isOptional() && ($next && !$next->isEmpty() && !$next->anyOfOptions($this->specs))) {
			$spec->setValue($next->arg);

			return 1;
		}

		return 0;
	}

	/**
	 * preprocess the argv array
	 *
	 * - split option and option value
	 * - separate arguments after "--"
	 *
	 * @param array $argv
	 * @return array
	 */
	protected function preprocessingArguments(array $argv)
	{
		// preprocessing arguments
		$newArgv = [];
		$extra = [];
		$afterDash = false;
		foreach ($argv as $arg) {
			if ($arg === '--') {
				$afterDash = true;
				continue;
			}
			if ($afterDash) {
				$extra[] = $arg;
				continue;
			}

			$a = new Argument($arg);
			if ($a->anyOfOptions($this->specs) && $a->containsOptionValue()) {
				[$opt, $val] = $a->splitAsOption();
				array_push($newArgv, $opt, $val);
			} else {
				$newArgv[] = $arg;
			}
		}

		return [$newArgv, $extra];
	}

	/**
	 * @param array $argv
	 *
	 * @return OptionResult|Option[]
	 *
	 * @throws RequireValueException
	 * @throws InvalidOptionException
	 * @throws \RuntimeException
	 */
	public function parse(array $argv)
	{
		$result = new OptionResult();
		[$argv] = $this->preprocessingArguments($argv);

		foreach ($this->specs as $opt) {
			if ($opt->defaultValue !== null) {
				$opt->setValue($opt->defaultValue);
				$result->set($opt->getId(), $opt);
			}
		}

		$len = count($argv);

		// some people might still pass only the option names here.
		$first = new Argument($argv[0]);
		if ($first->isOption()) {
			throw new \RuntimeException('parse(argv) expects the first argument to be the program name.');
		}

		for ($i = 1; $i < $len; ++$i) {
			$arg = new Argument($argv[$i]);

			// if looks like not an option, push it to argument list.
			// TODO: we might want to support argument with preceding dash (?)
			if (!$arg->isOption()) {
				$result->addArgument($arg);
				continue;
			}

			// if the option is with extra flags,
			//   split the string, and insert into the argv array
			if ($arg->withExtraFlagOptions()) {
				$extra = $arg->extractExtraFlagOptions();
				array_splice($argv, $i + 1, 0, $extra);
				$argv[$i] = $arg->arg; // update argument to current argv list.
				$len = count($argv);   // update argv list length
			}

			$next = null;
			if ($i + 1 < count($argv)) {
				$next = new Argument($argv[$i + 1]);
			}

			$spec = $this->specs->get($arg->getOptionName());
			if (!$spec) {
				throw new InvalidOptionException('Invalid option: ' . $arg);
			}

			// This if expr might be unnecessary, becase we have default mode - flag
			// if ($spec->isRequired() || $spec->isMultiple() || $spec->isOptional() || $spec->isFlag()) {
			$i += $this->consumeOptionToken($spec, $arg, $next);
			$result->set($spec->getId(), $spec);
		}

		return $result;
	}
}
