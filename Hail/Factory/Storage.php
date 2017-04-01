<?php
namespace Hail\Factory;

use Hail\Filesystem\{
	MountManager,
	Filesystem,
	FilesystemInterface
};
use Hail\Facade\{
	Config, Serialize
};

class Storage extends Factory
{
	/**
	 * @param array $config
	 *
	 * @return MountManager
	 * @throws \InvalidArgumentException
	 */
	public static function mount(array $config = []): MountManager
	{
		$config += Config::get('filesystem');
		$hash = sha1(Serialize::encode($config));

		return static::$pool[$hash] ?? (static::$pool[$hash] = new MountManager($config));
	}

	/**
	 * @param array $config
	 *
	 * @return FilesystemInterface
	 * @throws \InvalidArgumentException
	 */
	public static function filesystem(array $config = []): FilesystemInterface
	{
		if (!isset($config['adapter'])) {
			return static::mount($config);
		}

		$hash = sha1(Serialize::encode($config));

		return static::$pool[$hash] ?? (static::$pool[$hash] = new Filesystem($config));
	}
}