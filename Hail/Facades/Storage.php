<?php
namespace Hail\Facades;

use Hail\Factory\Storage as StorageFactory;
use Hail\Filesystem\{
	MountManager,
	AdapterInterface,
	Handler,
	Filesystem
};

/**
 * Class Filesystem
 *
 * @package Hail\Facades
 *
 * @method static AdapterInterface getAdapter($prefix)
 * @method static array getConfig($prefix)
 * @method static bool has(string $path)
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
 * @method static Handler get($path, Handler $handler = null)
 * @method static void assertPresent($path)
 * @method static void assertAbsent($path)
 * @method static MountManager mountFilesystems(array $filesystems)
 * @method static MountManager mountFilesystem(string $prefix, array|Filesystem $filesystem)
 * @method static Filesystem getFilesystem($prefix)
 * @method static array filterPrefix(array $arguments)
 * @method static array listContents($directory = '', $recursive = false)
 * @method static bool copy($from, $to, array $config = [])
 * @method static mixed listWith(array $keys = [], $directory = '', $recursive = false)
 * @method static bool move($from, $to, array $config = [])
 * @method static bool forceRename(string $path, string $newpath)
 */
class Storage extends Facade
{
	protected static function instance()
	{
		return StorageFactory::mount();
	}
}