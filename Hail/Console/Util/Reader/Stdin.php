<?php
namespace Hail\Console\Util\Reader;

class Stdin
{
	/**
	 * @var resource
	 */
	protected $stdIn = false;

	/**
	 * Read the line typed in by the user
	 *
	 * @return string
	 * @throws \RuntimeException
	 */
	public function line()
	{
		return trim(fgets($this->getStdIn(), 1024));
	}

	/**
	 * Read from STDIN until EOF (^D) is reached
	 *
	 * @return string
	 * @throws \RuntimeException
	 */
	public function multiLine()
	{
		return trim(stream_get_contents($this->getStdIn()));
	}

	/**
	 * Read one character
	 *
	 * @param int $count
	 *
	 * @return string
	 * @throws \RuntimeException
	 */
	public function char($count = 1)
	{
		return fread($this->getStdIn(), $count);
	}

	/**
	 * Read the line, but hide what the user is typing
	 *
	 * @return string
	 * @throws \RuntimeException
	 */
	public function hidden()
	{
		// handle windows
		if (defined('PHP_WINDOWS_VERSION_BUILD')) {
			// fallback to hiddeninput executable
			$exe = __DIR__ . '\\..\\bin\\hiddeninput.exe';
			// handle code running from a phar
			if (0 === strpos(__FILE__, 'phar:')) {
				$tmpExe = sys_get_temp_dir() . '/hiddeninput.exe';
				// use stream_copy_to_stream instead of copy
				// to work around https://bugs.php.net/bug.php?id=64634
				$source = fopen($exe, 'rb');
				$target = fopen($tmpExe, 'w+b');
				stream_copy_to_stream($source, $target);
				fclose($source);
				fclose($target);
				unset($source, $target);
				$exe = $tmpExe;
			}
			$answer = self::trimAnswer(shell_exec($exe));
			// clean up
			if (isset($tmpExe)) {
				unlink($tmpExe);
			}
			// output a newline to be on par with the regular prompt()
			echo PHP_EOL;

			return $answer;
		}

		if (file_exists('/usr/bin/env')) {
			// handle other OSs with bash/zsh/ksh/csh if available to hide the answer
			$test = "/usr/bin/env %s -c 'echo OK' 2> /dev/null";
			foreach (['bash', 'zsh', 'ksh', 'csh', 'sh'] as $sh) {
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

		// not able to hide the answer
		throw new \RuntimeException('Could not prompt for input in a secure fashion, aborting');
	}

	private static function trimAnswer($str)
	{
		return preg_replace('{\r?\n$}D', '', $str);
	}

	/**
	 * Return a valid STDIN, even if it previously EOF'ed
	 *
	 * Lazily re-opens STDIN after hitting an EOF
	 *
	 * @return resource
	 * @throws \RuntimeException
	 */
	protected function getStdIn()
	{
		if ($this->stdIn && !feof($this->stdIn)) {
			return $this->stdIn;
		}

		try {
			$this->setStdIn();
		} catch (\Error $e) {
			throw new \RuntimeException('Unable to read from STDIN', 0, $e);
		}

		return $this->stdIn;
	}

	/**
	 * Attempt to set the stdin property
	 *
	 * @throws \RuntimeException
	 */
	protected function setStdIn()
	{
		if ($this->stdIn !== false) {
			fclose($this->stdIn);
		}

		$this->stdIn = fopen('php://stdin', 'rb');

		if (!$this->stdIn) {
			throw new \RuntimeException('Unable to read from STDIN');
		}
	}
}
