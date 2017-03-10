<?php

namespace Hail\Filesystem;

use Hail\Filesystem\Exception\{
	FileNotFoundException,
	FilesystemNotFoundException
};
use InvalidArgumentException;
use LogicException;


/**
 * Class MountManager.
 *
 * Proxies methods to Filesystem (@see __call):
 *
 * @method AdapterInterface getAdapter($prefix)
 * @method array getConfig($prefix)
 * @method bool has(string $path)
 * @method bool write($path, $contents, array $config = [])
 * @method bool writeStream($path, $resource, array $config = [])
 * @method bool put($path, $contents, array $config = [])
 * @method bool putStream($path, $contents, array $config = [])
 * @method string readAndDelete($path)
 * @method bool update($path, $contents, array $config = [])
 * @method bool updateStream($path, $resource, array $config = [])
 * @method string|false read($path)
 * @method resource|false readStream($path)
 * @method bool rename($path, $newpath)
 * @method bool delete($path)
 * @method bool deleteDir($dirname)
 * @method bool createDir($dirname, array $config = [])
 * @method array getWithMetadata($path, array $metadata)
 * @method string|false getMimetype($path)
 * @method string|false getTimestamp($path)
 * @method string|false getVisibility($path)
 * @method int|false getSize($path);
 * @method bool setVisibility($path, $visibility)
 * @method array|false getMetadata($path)
 * @method Handler get($path, Handler $handler = null)
 * @method void assertPresent($path)
 * @method void assertAbsent($path)
 */
class MountManager implements FilesystemInterface
{
	use PluginTrait;

	/**
	 * @var FilesystemInterface[]
	 */
	protected $filesystems = [];

	/**
	 * @var array
	 */
	protected $lazy = [];

	/**
	 * Constructor.
	 *
	 * @param FilesystemInterface[] $filesystems [:prefix => Filesystem,]
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(array $filesystems = [])
	{
		$this->mountFilesystems($filesystems);
	}

	/**
	 * Mount filesystems.
	 *
	 * @param FilesystemInterface[] $filesystems [:prefix => Filesystem,]
	 *
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function mountFilesystems(array $filesystems)
	{
		foreach ($filesystems as $prefix => $filesystem) {
			$this->mountFilesystem($prefix, $filesystem);
		}

		return $this;
	}

	/**
	 * Mount filesystems.
	 *
	 * @param string           $prefix
	 * @param array|Filesystem $filesystem
	 *
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function mountFilesystem(string $prefix, $filesystem)
	{
		if (is_array($filesystem)) {
			$this->lazy[$prefix] = $filesystem;
		} else {
			$this->filesystems[$prefix] = $filesystem;
		}

		return $this;
	}

	/**
	 * Get the filesystem with the corresponding prefix.
	 *
	 * @param string $prefix
	 *
	 * @return FilesystemInterface
	 * @throws FilesystemNotFoundException
	 * @throws InvalidArgumentException
	 */
	public function getFilesystem(string $prefix)
	{
		if (!isset($this->filesystems[$prefix])) {
			if (isset($this->lazy[$prefix])) {
				return $this->filesystems[$prefix] = new Filesystem($this->lazy[$prefix]);
			}

			throw new FilesystemNotFoundException('No filesystem mounted with prefix ' . $prefix);
		}

		return $this->filesystems[$prefix];
	}

	/**
	 * Retrieve the prefix from an arguments array.
	 *
	 * @param array $arguments
	 *
	 * @return array [:prefix, :arguments]
	 * @throws InvalidArgumentException
	 */
	public function filterPrefix(array $arguments)
	{
		if (empty($arguments)) {
			throw new InvalidArgumentException('At least one argument needed');
		}

		$path = array_shift($arguments);

		if (!is_string($path)) {
			throw new InvalidArgumentException('First argument should be a string');
		}

		[$prefix, $path] = $this->getPrefixAndPath($path);
		array_unshift($arguments, $path);

		return [$prefix, $arguments];
	}

	/**
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 * @throws FilesystemNotFoundException
	 */
	public function listContents($directory = '', $recursive = false)
	{
		[$prefix, $directory] = $this->getPrefixAndPath($directory);
		$filesystem = $this->filesystems[$prefix] ?? $this->getFilesystem($prefix);
		$result = $filesystem->listContents($directory, $recursive);

		foreach ($result as &$file) {
			$file['filesystem'] = $prefix;
		}

		return $result;
	}

	/**
	 * Call forwarder.
	 *
	 * @param string $method
	 * @param array  $arguments
	 *
	 * @return mixed
	 * @throws InvalidArgumentException
	 * @throws FilesystemNotFoundException
	 */
	public function __call($method, $arguments)
	{
		list($prefix, $args) = $this->filterPrefix($arguments);

		$fs = $this->filesystems[$prefix] ?? $this->getFilesystem($prefix);

		return $fs->$method(...$args);
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @param array  $config
	 *
	 * @return bool
	 * @throws InvalidArgumentException
	 * @throws FilesystemNotFoundException
	 */
	public function copy($from, $to, array $config = [])
	{
		[$prefixFrom, $from] = $this->getPrefixAndPath($from);

		$fsFrom = $this->filesystems[$prefixFrom] ?? $this->getFilesystem($prefixFrom);
		$buffer = $fsFrom->readStream($from);

		if ($buffer === false) {
			return false;
		}

		[$prefixTo, $to] = $this->getPrefixAndPath($to);

		$fsTo = $this->filesystems[$prefixTo] ?? $this->getFilesystem($prefixTo);
		$result = $fsTo->writeStream($to, $buffer, $config);

		if (is_resource($buffer)) {
			fclose($buffer);
		}

		return $result;
	}

	/**
	 * List contents with metadata.
	 *
	 * @param array  $keys
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return mixed
	 */
	public function listWith(array $keys = [], $directory = '', $recursive = false)
	{
		[$prefix, $directory] = $this->getPrefixAndPath($directory);
		$fs = $this->filesystems[$prefix] ?? $this->getFilesystem($prefix);

		return $fs->listWith($keys, $directory, $recursive);
	}

	/**
	 * Move a file.
	 *
	 * @param       $from
	 * @param       $to
	 * @param array $config
	 *
	 * @return bool
	 * @throws InvalidArgumentException
	 * @throws FilesystemNotFoundException
	 */
	public function move($from, $to, array $config = [])
	{
		$copied = $this->copy($from, $to, $config);

		if ($copied) {
			return $this->delete($from);
		}

		return false;
	}

	/**
	 * Renames a file, overwriting the destination if it exists.
	 *
	 * @param string $path    Path to the existing file.
	 * @param string $newpath The new path of the file.
	 *
	 * @return bool True on success, false on failure.
	 * @throws FileNotFoundException Thrown if $path does not exist.
	 * @throws FilesystemNotFoundException
	 * @throws InvalidArgumentException
	 */
	public function forceRename(string $path, string $newpath)
	{
		$deleted = true;
		if ($this->has($newpath)) {
			$deleted = $this->delete($newpath);
		}

		if ($deleted) {
			return $this->move($path, $newpath);
		}

		return false;
	}

	/**
     * @param string $path
     *
     * @throws InvalidArgumentException
     *
     * @return string[] [:prefix, :path]
     */
    protected function getPrefixAndPath($path)
    {
        if (strpos($path, '://') < 1) {
            throw new InvalidArgumentException('No prefix detected in path: ' . $path);
        }

        return explode('://', $path, 2);
    }
}
