<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Hail\SimpleCache;


/**
 * Base file cache driver.
 *
 * @since  2.3
 * @author Fabio B. Silva <fabio.bat.silva@gmail.com>
 * @author Tobias Schultze <http://tobion.de>
 * @author Hao Feng <flyinghail@msn.com>
 */
class File extends AbtractAdapter
{
	const EXTENSION = '.cache.php';
	const EXTENSION_LENGTH = 10;

	/**
	 * The cache directory.
	 *
	 * @var string
	 */
	protected $directory;

	/**
	 * @var int
	 */
	private $umask;

	/**
	 * Constructor.
	 *
	 * @param array $params [directory => The cache directory].
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct($params)
	{
		$umask = $params['umask'] ?? 0002;
		// YES, this needs to be *before* createPathIfNeeded()
		if (!is_int($umask)) {
			throw new \InvalidArgumentException('The umask parameter is required to be integer, was: ' . gettype($umask));
		}
		$this->umask = $umask;

		$directory = $params['directory'] ?? TEMP_PATH . 'cache/';
		if (!$this->createPathIfNeeded($directory)) {
			throw new \InvalidArgumentException("The directory '$directory' does not exist and could not be created.");
		}

		if (!is_writable($directory)) {
			throw new \InvalidArgumentException("The directory '$directory' is not writable.");
		}

		// YES, this needs to be *after* createPathIfNeeded()
		$this->directory = realpath($directory) . '/';

		parent::__construct($params);
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function getFilename(string $key): string
	{
		return $this->directory . $key . self::EXTENSION;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		$filename = $this->getFilename($key);

		return @unlink($filename) || !file_exists($filename);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		foreach ($this->getIterator() as $name => $file) {
			if ($file->isDir()) {
				// Remove the intermediate directories which have been created to balance the tree. It only takes effect
				// if the directory is empty. If several caches share the same directory but with different file extensions,
				// the other ones are not removed.
				@rmdir($name);
			} elseif ($this->isFilenameEndingWithExtension($name)) {
				// If an extension is set, only remove files which end with the given extension.
				// If no extension is set, we have no other choice than removing everything.
				@unlink($name);
			}
		}

		return true;
	}

	/**
	 * Create path if needed.
	 *
	 * @param string $path
	 *
	 * @return bool TRUE on success or if path already exists, FALSE if path cannot be created.
	 */
	private function createPathIfNeeded($path)
	{
		if (!is_dir($path)) {
			if (false === @mkdir($path, 0777 & (~$this->umask), true) && !is_dir($path)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Writes a string content to file in an atomic way.
	 *
	 * @param string $filename Path to the file where to write the data.
	 * @param string $content  The content to write
	 *
	 * @return bool TRUE on success, FALSE if path cannot be created, if path is not writable or an any other error.
	 */
	protected function writeFile($filename, $content)
	{
		$filepath = pathinfo($filename, PATHINFO_DIRNAME);

		if (!$this->createPathIfNeeded($filepath)) {
			return false;
		}

		if (!is_writable($filepath)) {
			return false;
		}

		$tmpFile = tempnam($filepath, 'swap');
		@chmod($tmpFile, 0666 & (~$this->umask));

		if (file_put_contents($tmpFile, $content) !== false) {
			if (@rename($tmpFile, $filename)) {
				return true;
			}

			@unlink($tmpFile);
		}

		return false;
	}

	/**
	 * @return \Iterator
	 */
	private function getIterator()
	{
		return new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($this->directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
	}

	/**
	 * @param string $name The filename
	 *
	 * @return bool
	 */
	private function isFilenameEndingWithExtension($name)
	{
		return strrpos($name, self::EXTENSION) === (strlen($name) - self::EXTENSION_LENGTH);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		return $this->includeFileForId($key);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		$value = $this->includeFileForId($key);

		return $value !== null;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		if ($ttl > 0) {
			$ttl = NOW + $ttl;
		}

		$filename = $this->getFilename($key);
		$code = '<?php return ';

		if ($ttl > 0) {
			$code .= '(NOW > ' . $ttl . ') ? null : ';
		}

		$code .= var_export($value, true) . ';';

		return $this->writeFile($filename, $code);
	}

	/**
	 * @param string $id
	 *
	 * @return array|false
	 */
	private function includeFileForId($id)
	{
		$fileName = $this->getFilename($id);

		if (file_exists($fileName)) {
			return include $fileName;
		}

		return null;
	}
}
