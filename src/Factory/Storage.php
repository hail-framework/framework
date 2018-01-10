<?php
namespace Hail\Factory;

use Hail\Filesystem\{
	MountManager,
	Filesystem,
	FilesystemInterface
};
use Hail\Util\Serialize;

class Storage extends Factory
{
	/**
	 * @param array $config
	 *
	 * @return MountManager
	 */
	public static function mount(array $config = []): MountManager
	{
		[$hash, $config] = static::getKey($config, 'filesystem');

		return static::$pool[$hash] ?? (static::$pool[$hash] = new MountManager($config));
	}

	/**
	 * @param array $config
	 *
	 * @return FilesystemInterface
	 */
	public static function filesystem(array $config = []): FilesystemInterface
	{
		return new Filesystem($config);
	}
}