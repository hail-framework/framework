<?php
namespace Hail\Facades;

use Hail\Filesystem\MountManager;

/**
 * Class Filesystem
 *
 * @package Hail\Facades
 *
 * @method static \Hail\Filesystem\AdapterInterface getAdapter($prefix)
 * @method static array getConfig($prefix)
 * @method static bool has($path)
 * @method static bool write($path, $contents, array $config = [])
 * @method static bool writeStream($path, $resource, array $config = [])
 * @method static bool put($path, $contents, $config = [])
 * @method static bool putStream($path, $contents, $config = [])
 * @method static string readAndDelete($path)
 * @method static bool update($path, $contents, $config = [])
 * @method static bool updateStream($path, $resource, $config = [])
 * @method static string|false read($path)
 * @method static resource|false readStream($path)
 * @method static bool rename($path, $newpath)
 * @method static bool delete($path)
 * @method static bool deleteDir($dirname)
 * @method static bool createDir($dirname, $config = [])
 * @method static array getWithMetadata($path, array $metadata)
 * @method static string|false getMimetype($path)
 * @method static string|false getTimestamp($path)
 * @method static string|false getVisibility($path)
 * @method static int|false getSize($path);
 * @method static bool setVisibility($path, $visibility)
 * @method static array|false getMetadata($path)
 * @method static \Hail\Filesystem\Handler get($path, \Hail\Filesystem\Handler $handler = null)
 * @method static void assertPresent($path)
 * @method static void assertAbsent($path)
 * @method static MountManager mountFilesystems(array $filesystems)
 * @method static MountManager mountFilesystem(string $prefix, array|\Hail\Filesystem\Filesystem $filesystem)
 * @method static \Hail\Filesystem\Filesystem getFilesystem($prefix)
 * @method static array filterPrefix(array $arguments)
 * @method static array listContents($directory = '', $recursive = false)
 * @method static bool copy($from, $to, array $config = [])
 * @method static mixed listWith(array $keys = [], $directory = '', $recursive = false)
 * @method static bool move($from, $to, array $config = [])
 * @method static bool forceRename(string $path, string $newpath)
 */
class Filesystem extends Facade
{
	protected static function instance()
	{
		return new MountManager(
			Config::get('filesystem')
		);
	}
}