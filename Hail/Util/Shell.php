<?php
namespace Hail\Util;

use Hail\Exception\ShellException;

class Shell
{
	/**
	 * If set to true, will output stderr to standard output, if set to a function, will send through function
	 *
	 * @var boolean
	 */
	static public $displayStderr = false;

	private static $output = '';
	private static $prepend = [];
	private static $stdin;

	public function __construct(array $prepend = [])
	{
		self::$prepend = $prepend;
	}

	public function __toString()
	{
		return self::$output;
	}

	private static function run(array $arguments)
	{
		// Unwind the args, figure out which ones were passed in as an array
		self::$stdin = null;
		$closureOut = false;
		foreach ($arguments as $k => $argument) {
			// If it's being passed in as an object, then pipe into stdin
			if (is_object($argument)) {
				// If it's a anonymous function, then push stdout into it
				if ($argument instanceof \Closure) {
					$closureOut = $argument;
				} else {
					self::$stdin = (string) $argument;
				}
				unset($arguments[$k]);
			} elseif (is_array($argument)) {
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
							if (strlen($key) === 1) {
								$output .= '-';
							} else {
								$output .= '--';
							}
							$output .= $key;
							// If you just pass in 'true', it'll just add the arg
							if ($val !== true) {
								$output .= ' ' . escapeshellarg($val);
							}
						}
					}
					$arguments[$k] = $output;
				} else {
					// We're passing in an array, but it's not --key=val style
					$arguments[$k] = implode(' ', $argument);
				}
			}
		}
		$shell = implode(' ', $arguments);

		// Prepend the path
		if (strpos(strtolower(PHP_OS), 'win') === false) {
			$parts = explode(' ', $shell);
			$parts[0] = exec('which ' . $parts[0]);
			if ($parts[0] !== '') {
				$shell = implode(' ', $parts);
			}
		}

		$descriptor_spec = [
			0 => ['pipe', 'r'], // Stdin
			1 => ['pipe', 'w'], // Stdout
			2 => ['pipe', 'w'] // Stderr
		];
		$process = proc_open($shell, $descriptor_spec, $pipes);
		if (is_resource($process)) {
			fwrite($pipes[0], self::$stdin);
			fclose($pipes[0]);
			$output = '';
			while (!feof($pipes[1])) {
				$stdout = fgets($pipes[1], 1024);
				if ($stdout === '') {
					break;
				}

				echo $stdout;

				if ($closureOut instanceof \Closure) {
					$closureOut($stdout);
				}
				$output .= $stdout;
			}
			$error_output = trim(stream_get_contents($pipes[2]));
			if (self::$displayStderr) {
				echo $error_output;
			}
			self::$output = $output;
			fclose($pipes[1]);
			fclose($pipes[2]);
			$return_value = proc_close($process);
			if ($return_value !== 0) {
				throw new ShellException($error_output, $return_value);
			}
		} else {
			throw new ShellException('Process failed to spawn');
		}
	}

	// Raw arguments
	public function __invoke(...$args)
	{
		self::run($args);

		return $this;
	}

	public function __call($name, $arguments)
	{
		array_unshift($arguments, $name);
		if ([] !== self::$prepend) {
			$arguments = array_merge(self::$prepend, $arguments);
		}
		self::run($arguments);

		return $this;
	}

	public static function __callStatic($name, $arguments)
	{
		array_unshift($arguments, $name);
		self::run($arguments);

		return new self();
	}
}