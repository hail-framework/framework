<?php
namespace Hail\SimpleCache\Adapter;

use Hail\Facades\Filesystem as FS;
use Hail\Facades\Serialize;

/**
 * Base file cache driver.
 *
 * @author Hao Feng <flyinghail@msn.com>
 */
class Filesystem extends AbtractAdapter
{
	const EXTENSION = '.cache.php';

	/**
	 * The cache directory.
	 *
	 * @var string
	 */
	protected $directory;

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
		FS::createDir($directory);

		parent::__construct($params);
	}

	/**
	 * @param string $key
	 *
	 * @return string
	 */
	protected function getFilename(string $key): string
	{
		return $this->directory . '/' . $key . self::EXTENSION;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		$filename = $this->getFilename($key);

		return FS::delete($filename);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		FS::deleteDir($this->directory);
		FS::createDir($this->directory);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$filename = $this->getFilename($key);

		try {
			if (($content = FS::read($filename)) === false) {
				return null;
			}

			$data = Serialize::decode($content);
		} catch (\Exception $e) {
			return null;
		}

		if ($data['expire'] > NOW) {
			FS::delete($filename);

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

		return FS::has($filename);
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
		$content = Serialize::encode([
			'value' => $value,
			'expire' => $ttl,
		]);

		return FS::put($filename, $content);
	}
}
