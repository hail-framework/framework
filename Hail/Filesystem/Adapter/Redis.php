<?php
namespace Hail\Filesystem\Adapter;

use Hail\Filesystem\Adapter\Polyfill\StreamedTrait as StreamPolyfill;
use Hail\Filesystem\Exception\FileNotFoundException;
use Hail\Filesystem\Util;
use Hail\Factory\Redis as RedisFactory;
use Hail\Facade\Serialize;

class Redis extends AbstractAdapter
{
	use StreamPolyfill;

	/**
	 * @type \Hail\Redis\Client\AbstractClient
	 */
	protected $redis;

	/**
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		if (!isset($config['client'])) {
			throw new \InvalidArgumentException('Config not defined');
		}

		$this->redis = RedisFactory::client($config['client']);
		$this->pathSeparator = ':';

		$this->setPathPrefix($config['prefix'] ?? 'HailFS:');
		$this->redis->hSetNx(
			$this->applyPathPrefix('/'), '.',
			Serialize::encode([
				'path' => '',
				'type' => 'dir',
				'visibility' => 'public',
				'timestamp' => NOW,
			])
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, array $config)
	{
		$info = $this->getPathInfo($path);

		if ($this->ensurePathExists($info['dirname'], $config)) {
			$fileData = [
				'path' => $info['path'],
				'type' => 'file',
				'contents' => base64_encode($contents),
				'visibility' => $config['visibility'] ?? 'public',
				'timestamp' => time(),
			];

			if (in_array($this->redis->hSet(
				$this->applyPathPrefix($info['dirname']),
				$info['basename'],
				Serialize::encode($fileData)),
				[0, 1], true
			)) {
				$fileData['contents'] = $contents;

				return $fileData;
			}
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($path)
	{
		$info = $this->getPathInfo($path);

		if ($this->redis->exists($this->applyPathPrefix($info['path']))) {
			// Is Directory...
			return true;
		} elseif ($this->redis->exists($this->applyPathPrefix($info['dirname']))
			&& $this->redis->hExists($this->applyPathPrefix($info['dirname']), $info['basename'])
		) {
			// Is File...
			return true;
		} else {
			// Doesn't Exist...
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($path)
	{
		$info = $this->getPathInfo($path);

		if (!$this->has($path)) {
			throw new FileNotFoundException("File not found at path: {$info['path']}");
		}

		$data = Serialize::decode($this->redis->hGet($this->applyPathPrefix($info['dirname']), $info['basename']));

		if ($data['type'] === 'file') {
			$data['contents'] = base64_decode($data['contents']);
			$data['size'] = Util::contentSize($data['contents']);

			return $data;
		} else {
			return '';
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata($path)
	{
		$info = $this->getPathInfo($path);

		if (!$this->has($path)) {
			throw new FileNotFoundException("File not found at path: {$info['path']}");
		}

		$metadata = Serialize::decode($this->redis->hGet($this->applyPathPrefix($info['dirname']), $info['basename']));

		if ($metadata['type'] === 'file') {
			$metadata['contents'] = base64_decode($metadata['contents']);
			$metadata += [
				'size' => Util::contentSize($metadata['contents']),
				'mimetype' => Util::guessMimeType($metadata['path'], $metadata['contents']),
			];

			unset ($metadata['contents']);
		}

		return $metadata;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSize($path)
	{
		$metadata = $this->getMetadata($path);

		return isset($metadata['size']) ? Util::map($metadata, ['size' => 'size']) : false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMimetype($path)
	{
		$metadata = $this->getMetadata($path);

		return isset($metadata['mimetype']) ? Util::map($metadata, ['mimetype' => 'mimetype']) : ['mimetype' => 'directory'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTimestamp($path)
	{
		$metadata = $this->getMetadata($path);

		return isset($metadata['timestamp']) ? Util::map($metadata, ['timestamp' => 'timestamp']) : false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($path, $contents, array $config)
	{
		if (!$this->has($path)) {
			return false;
		} else {
			return $this->write($path, $contents, $config);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($path, $newpath)
	{
		return $this->copy($path, $newpath) && $this->delete($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($path, $newpath)
	{
		return $this->write($newpath, $this->read($path)['contents'], []) !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path)
	{
		$info = $this->getPathInfo($path);

		if ($this->has($info['path']) && $this->getMetadata($info['path'])['type'] === 'file') {
			return $this->redis->hDel($this->applyPathPrefix($info['dirname']), $info['basename']) > 0;
		} else {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function createDir($dirname, array $config)
	{
		$info = $this->getPathInfo($dirname);
		$status = [
			'path' => $info['path'],
			'type' => 'dir',
			'visibility' => $config['visibility'] ?? 'public',
			'timestamp' => NOW,
		];

		if (!$this->has($info['path'])) {
			if (!$this->ensurePathExists($info['dirname'], $config)
				|| !($this->redis->hSetNx($this->applyPathPrefix($info['path']), '.', Serialize::encode($status))
					&& $this->redis->hSetNx($this->applyPathPrefix($info['dirname']), $info['basename'], Serialize::encode($status)))
			) {
				$status = false;
			}
		} elseif ($this->getMetadata($info['path'])['type'] === 'file') {
			$status = false;
		}

		return $status;
	}

	/**
	 * {@inheritdoc}
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$info = $this->getPathInfo($directory);

		$result = [];

		if ($this->has($info['path']) && $this->getMetadata($info['path'])['type'] === 'dir') {
			$files = $this->redis->hGetAll($this->applyPathPrefix($info['path']));
			ksort($files);

			foreach ($files as $name => $data) {
				if ($name === '.') {
					continue;
				}

				$data = Serialize::decode($data);

				$result[] = Util::map($data, ['type' => 'type', 'path' => 'path', 'timestamp' => 'timestamp', 'size' => 'size']);

				if ($recursive === true && $data['type'] === 'dir') {
					$result = array_merge($result, $this->listContents($data['path'], true));
				}
			}
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($dirname)
	{
		$info = $this->getPathInfo($dirname);

		if ($this->has($info['path']) && $this->getMetadata($info['path'])['type'] === 'dir') {
			$status = true;

			foreach (array_reverse($this->listContents($info['path'], true)) as $file) {
				if ($file['type'] === 'dir') {
					$status = $status && $this->deleteDir($file['path']);
				} else {
					$status = $status && $this->delete($file['path']);
				}
			}

			if ($info['path'] !== '/') {
				$status = $status && ($this->redis->hDel($this->applyPathPrefix($info['dirname']), $info['basename']) > 0);
			}

			return $status && ($this->redis->del($this->applyPathPrefix($info['path'])) > 0);
		} else {
			return false;
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function setVisibility($path, $visibility)
	{
		$info = $this->getPathInfo($path);

		if ($this->has($info['path'])) {
			$data = Serialize::decode($this->redis->hGet($this->applyPathPrefix($info['dirname']), $info['basename']));
			$data['visibility'] = $visibility;

			if (in_array($this->redis->hSet($this->applyPathPrefix($info['dirname']), $info['basename'], Serialize::encode($data)), [0, 1], true)) {
				return $data;
			}
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getVisibility($path)
	{
		$metadata = $this->getMetadata($path);

		return isset($metadata['visibility']) ? Util::map($metadata, ['visibility' => 'visibility']) : ['visibility' => 'public'];
	}

	protected function getPathInfo($path)
	{
		$info = Util::pathinfo('/' . Util::normalizePath($path));
		$info['path'] = ltrim($info['path'], '/');
		$info['dirname'] = ltrim($info['dirname'], '/');
		if (empty($info['basename'])) {
			$info['basename'] = '.';
		}

		return $info;
	}

	protected function ensurePathExists($path, array $config)
	{
		if ($this->has($path)) {
			if ($this->getMetadata($path)['type'] === 'dir') {
				return true;
			} else {
				return false;
			}
		} else {
			$info = $this->getPathInfo($path);

			return is_array($this->createDir($info['path'], $config));
		}
	}
}

