<?php

namespace Hail\Filesystem;

use Hail\Filesystem\{
	Exception\FileNotFoundException
};

/**
 * Class PluginTrait
 *
 * @package Hail\Filesystem
 */
trait PluginTrait
{
	/**
	 * Copies a file, overwriting any existing files.
	 *
	 * @param string $path    Path to the existing file.
	 * @param string $newpath The new path of the file.
	 *
	 * @return bool True on success, false on failure.
	 * @throws FileNotFoundException Thrown if $path does not exist.
	 */
	public function forceCopy(string $path, string $newpath)
	{
		$deleted = true;
		if ($this->has($newpath)) {
			$deleted = $this->delete($newpath);
		}

		if ($deleted) {
			return $this->copy($path, $newpath);
		}

		return false;
	}

	/**
	 * List all files in the directory.
	 *
	 * @param string $path
	 * @param bool   $recursive
	 *
	 * @return array
	 */
	public function listFiles(string $path = '', bool $recursive = false)
	{
		$contents = $this->listContents($path, $recursive);

		$filter = function ($object) {
			return $object['type'] === 'file';
		};

		return \array_values(\array_filter($contents, $filter));
	}

	/**
	 * List all paths.
	 *
	 * @param string $path
	 * @param bool   $recursive
	 *
	 * @return array paths
	 */
	public function listPaths(string $path = '', bool $recursive = false)
	{
		$result = [];
		$contents = $this->listContents($path, $recursive);

		foreach ($contents as $object) {
			$result[] = $object['path'];
		}

		return $result;
	}
}
