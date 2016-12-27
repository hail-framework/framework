<?php

namespace Hail\Filesystem;

use Hail\Filesystem\Exception\FileNotFoundException;
use InvalidArgumentException;
use LogicException;


/**
 * Class MountManager.
 *
 * Proxies methods to Filesystem (@see __call):
 *
 * @method AdapterInterface getAdapter($prefix)
 * @method array getConfig($prefix)
 * @method bool has($path)
 * @method bool write($path, $contents, array $config = [])
 * @method bool writeStream($path, $resource, array $config = [])
 * @method bool put($path, $contents, $config = [])
 * @method bool putStream($path, $contents, $config = [])
 * @method string readAndDelete($path)
 * @method bool update($path, $contents, $config = [])
 * @method bool updateStream($path, $resource, $config = [])
 * @method string|false read($path)
 * @method resource|false readStream($path)
 * @method bool rename($path, $newpath)
 * @method bool delete($path)
 * @method bool deleteDir($dirname)
 * @method bool createDir($dirname, $config = [])
 * @method array getWithMetadata($path, array $metadata)
 * @method string|false getMimetype($path)
 * @method string|false getTimestamp($path)
 * @method string|false getVisibility($path)
 * @method int|false getSize($path);
 * @method bool setVisibility($path, $visibility)
 * @method array|false getMetadata($path)
 * @method Handler get($path, Handler $handler = null)
 * @method Filesystem flushCache()
 * @method void assertPresent($path)
 * @method void assertAbsent($path)
 */
class MountManager
{
	use PluginTrait;

	/**
	 * @var array
	 */
	protected $filesystems = [];

	/**
	 * Constructor.
	 *
	 * @param array $filesystems
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
	 * @param array $filesystems [:prefix => Filesystem,]
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
			$filesystem = new Filesystem($filesystem);
		}

		$this->filesystems[$prefix] = $filesystem;

		return $this;
	}

	/**
	 * Get the filesystem with the corresponding prefix.
	 *
	 * @param string $prefix
	 *
	 * @return Filesystem
	 * @throws LogicException
	 */
	public function getFilesystem($prefix)
	{
		if (!isset($this->filesystems[$prefix])) {
			throw new LogicException('No filesystem mounted with prefix ' . $prefix);
		}

		return $this->filesystems[$prefix];
	}

	/**
	 * Retrieve the prefix from an arguments array.
	 *
	 * @param array $arguments
	 *
	 * @return array [:prefix, :arguments]
	 * @throws LogicException
	 */
	public function filterPrefix(array $arguments)
	{
		if (empty($arguments)) {
			throw new LogicException('At least one argument needed');
		}

		$path = array_shift($arguments);

		if (!is_string($path)) {
			throw new InvalidArgumentException('First argument should be a string');
		}

		if (!preg_match('#^.+\:\/\/.*#', $path)) {
			throw new InvalidArgumentException('No prefix detected in path: ' . $path);
		}

		list($prefix, $path) = explode('://', $path, 2);
		array_unshift($arguments, $path);

		return [$prefix, $arguments];
	}

	/**
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return array
	 */
	public function listContents($directory = '', $recursive = false)
	{
		list($prefix, $arguments) = $this->filterPrefix([$directory]);
		$filesystem = $this->getFilesystem($prefix);
		$directory = array_shift($arguments);
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
	 */
	public function __call($method, $arguments)
	{
		list($prefix, $args) = $this->filterPrefix($arguments);

		$fs = $this->getFilesystem($prefix);

		switch (count($args)) {
			case 0:
				return $fs->$method();
			case 1:
				return $fs->$method($args[0]);
			case 2:
				return $fs->$method($args[0], $args[1]);
			case 3:
				return $fs->$method($args[0], $args[1], $args[2]);
			case 4:
				return $fs->$method($args[0], $args[1], $args[2], $args[3]);
			default:
				return call_user_func_array([$fs, $method], $args);
		}
	}

	/**
	 * @param       $from
	 * @param       $to
	 * @param array $config
	 *
	 * @return bool
	 */
	public function copy($from, $to, array $config = [])
	{
		list($prefixFrom, $arguments) = $this->filterPrefix([$from]);

		$fsFrom = $this->getFilesystem($prefixFrom);
		$buffer = $fsFrom->readStream($arguments[0]);

		if ($buffer === false) {
			return false;
		}

		list($prefixTo, $arguments) = $this->filterPrefix([$to]);

		$fsTo = $this->getFilesystem($prefixTo);
		$result = $fsTo->writeStream($arguments[0], $buffer, $config);

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
		list($prefix, $arguments) = $this->filterPrefix([$directory]);
		$fs = $this->getFilesystem($prefix);

		return $fs->listWith($keys, $arguments[0], $recursive);
	}

	/**
	 * Move a file.
	 *
	 * @param       $from
	 * @param       $to
	 * @param array $config
	 *
	 * @return bool
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
}
