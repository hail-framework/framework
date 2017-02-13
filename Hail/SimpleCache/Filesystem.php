<?php
namespace Hail\SimpleCache;

use Hail\Factory\Storage;
use Hail\Facades\Serialize;
use Hail\Filesystem\{
	FilesystemInterface,
	MountManager
};

/**
 * Base file cache driver.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class Filesystem extends AbstractAdapter
{
	const EXTENSION = '.cache.php';

	/**
	 * The cache directory.
	 *
	 * @var string
	 */
	protected $directory;

	/**
	 * @var FilesystemInterface
	 */
	protected $storage;

	/**
	 * Constructor.
	 *
	 * @param array $params [prefix => mount prefix, directory => The cache directory].
	 *
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 */
	public function __construct($params)
	{
		$directory = $params['directory'] ?? 'cache';
		if (!preg_match('/^[a-z]:\/\//', $directory)) {
			throw new \InvalidArgumentException('Directory does not conform to Filesystem protocol');
		}

		$storage = Storage::filesystem($params['storage'] ?? []);

		$prefix = $params['prefix'] ?? '';
		if ($prefix && $storage instanceof MountManager) {
			$storage = $storage->getFilesystem($prefix);
		}

		$storage->createDir($directory);

		$this->directory = $directory;
		$this->storage = $storage;

		parent::__construct($params);
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	protected function getFilename(string $key): string
	{
		$sub = substr(hash('sha256', $key), 0, 2);
		return $this->directory . '/' . $sub . '/' . base64_encode($key) . self::EXTENSION;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		$filename = $this->getFilename($key);

		return $this->storage->delete($filename);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		$this->storage->deleteDir($this->directory);
		$this->storage->createDir($this->directory);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$filename = $this->getFilename($key);

		try {
			if (($content = $this->storage->read($filename)) === false) {
				return null;
			}

			$content = substr($content, 8, -2);
			$data = Serialize::decode($content);
		} catch (\Exception $e) {
			return null;
		}

		if ($data['expire'] > NOW) {
			$this->storage->delete($filename);

			return null;
		}

		return $data['value'];
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		$filename = $this->getFilename($key);

		return $this->storage->has($filename);
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
		$content = '<?php /*' . Serialize::encode([
				'value' => $value,
				'expire' => $ttl,
			]) . '*/';

		return $this->storage->put($filename, $content);
	}
}
