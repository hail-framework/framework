<?php
namespace Hail\CLImate\Util\Reader;

class Stdin implements ReaderInterface
{
	/**
	 * Read the line typed in by the user
	 *
	 * @return string
	 */
	public function line()
	{
		$response = trim(fgets(STDIN, 1024));

		return $response;
	}

	/**
	 * Read one character
	 *
	 * @param int $count
	 *
	 * @return string
	 */
	public function char($count = 1)
	{
		return fread(STDIN, $count);
	}

	/**
	 * Read the line, but hide what the user is typing
	 * Code from CLI Prompt (c) Jordi Boggiano <j.boggiano@seld.be>
	 *
	 * @return string
	 * @throws \RuntimeException on failure to prompt, unless $allowFallback is true
	 */
	public function hidden()
	{
		// handle windows
		if (defined('PHP_WINDOWS_VERSION_BUILD')) {
			// fallback to hiddeninput executable
			$answer = self::trimAnswer(shell_exec(__DIR__ . '\\hiddeninput.exe'));
			// output a newline to be on par with the regular prompt()
			echo PHP_EOL;
			return $answer;
		} elseif (file_exists('/usr/bin/env')) {
			// handle other OSs with bash/zsh/ksh/csh if available to hide the answer
			$test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
			foreach (array('bash', 'zsh', 'ksh', 'csh') as $sh) {
				if ('OK' === rtrim(shell_exec(sprintf($test, $sh)))) {
					$shell = $sh;
					break;
				}
			}
			if (isset($shell)) {
				$readCmd = ($shell === 'csh') ? 'set mypassword = $<' : 'read -r mypassword';
				$command = sprintf("/usr/bin/env %s -c 'stty -echo; %s; stty echo; echo \$mypassword'", $shell, $readCmd);
				$value = self::trimAnswer(shell_exec($command));
				// output a newline to be on par with the regular prompt()
				echo PHP_EOL;
				return $value;
			}
		}

		throw new \RuntimeException('Could not prompt for input in a secure fashion, aborting');
	}

	private static function trimAnswer($str)
	{
		return preg_replace('{\r?\n$}D', '', $str);
	}
}
