<?php
namespace Hail\SimpleCache;

use Hail\Facades\Filesystem as FS;
use Hail\Facades\Serialize;
use Hail\Filesystem\MountManager;

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
	 * @var MountManager
	 */
	protected $filesystem;

	/**
	 * Constructor.
	 *
	 * @param array $params [directory => The cache directory].
	 *
	 * @throws \InvalidArgumentException
	 */
	public function __construct($params)
	{
		if (!preg_match('/^[a-z]:\/\//', $params['directory'])) {
			throw new \InvalidArgumentException('Directory does not conform to Filesystem protocol');
		}

		$this->directory = $directory = $params['directory'] ?? 'local://cache';
		$this->filesystem = FS::getInstance();

		$this->filesystem->createDir($directory);

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

		return $this->filesystem->delete($filename);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		$this->filesystem->deleteDir($this->directory);
		$this->filesystem->createDir($this->directory);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$filename = $this->getFilename($key);

		try {
			if (($content = $this->filesystem->read($filename)) === false) {
				return null;
			}

			$content = substr($content, 8, -2);
			$data = Serialize::decode($content);
		} catch (\Exception $e) {
			return null;
		}

		if ($data['expire'] > NOW) {
			$this->filesystem->delete($filename);

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

		return $this->filesystem->has($filename);
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

		return $this->filesystem->put($filename, $content);
	}
}
