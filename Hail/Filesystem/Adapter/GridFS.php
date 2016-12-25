<?php

namespace Hail\Filesystem\Adapter;

use BadMethodCallException;
use Hail\Filesystem\Adapter\Polyfill\{
	NotSupportingVisibilityTrait,
	StreamedCopyTrait,
	StreamedReadingTrait
};
use Hail\Filesystem\Util;
use InvalidArgumentException;
use LogicException;
use MongoGridFs;
use MongoGridFSException;
use MongoGridFSFile;
use MongoRegex;

class GridFS extends AbstractAdapter
{
	use NotSupportingVisibilityTrait;
	use StreamedCopyTrait;
	use StreamedReadingTrait;

	/**
	 * @var MongoGridFs Mongo GridFS client
	 */
	protected $client;

	/**
	 * Constructor.
	 *
	 * @param array $config
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct(array $config)
	{
		if (!isset($config['client']) || ($client = $config['client']) instanceof MongoGridFs) {
			throw new \InvalidArgumentException('MongoGridFs client not defined');
		}

		$this->client = $client;
	}

	/**
	 * Get the MongoGridFs instance.
	 *
	 * @return MongoGridFs
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($path)
	{
		$location = $this->applyPathPrefix($path);

		return $this->client->findOne($location) !== null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, array $config)
	{
		$metadata = [];

		if (isset($config['mimetype'])) {
			$metadata['mimetype'] = $config['mimetype'];
		}

		return $this->writeObject($path, $contents, [
			'filename' => $path,
			'metadata' => $metadata,
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function writeStream($path, $resource, array $config)
	{
		return $this->write($path, $resource, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($path, $contents, array $config)
	{
		return $this->write($path, $contents, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateStream($path, $resource, array $config)
	{
		return $this->writeStream($path, $resource, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata($path)
	{
		$result = $this->client->findOne($path);

		return $this->normalizeGridFSFile($result, $path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMimetype($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSize($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTimestamp($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path)
	{
		$file = $this->client->findOne($path);

		return $file && $this->client->delete($file->file['_id']) !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($path)
	{
		$file = $this->client->findOne($path);

		return $file ? ['contents' => $file->getBytes()] : false;
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
	public function createDir($path, array $config)
	{
		throw new LogicException(get_class($this) . ' does not support directory creation.');
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($path)
	{
		$prefix = rtrim($this->applyPathPrefix($path), '/') . '/';

		$result = $this->client->remove([
			'filename' => new MongoRegex('/^' . $prefix . '/'),
		]);

		return $result === true;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @todo Implement recursive listing.
	 */
	public function listContents($dirname = '', $recursive = false)
	{
		if ($recursive) {
			throw new BadMethodCallException('Recursive listing is not yet implemented');
		}

		$keys = [];
		$cursor = $this->client->find([
			'filename' => new MongoRegex('/^' . $dirname . '/'),
		]);
		foreach ($cursor as $file) {
			$keys[] = $this->normalizeGridFSFile($file);
		}

		return Util::emulateDirectories($keys);
	}

	/**
	 * Write an object to GridFS.
	 *
	 * @param array $metadata
	 *
	 * @return array|false normalized file representation
	 */
	protected function writeObject($path, $content, array $metadata)
	{
		try {
			if (is_resource($content)) {
				$id = $this->client->storeFile($content, $metadata);
			} else {
				$id = $this->client->storeBytes($content, $metadata);
			}
		} catch (MongoGridFSException $e) {
			return false;
		}

		$file = $this->client->findOne(['_id' => $id]);

		return $this->normalizeGridFSFile($file, $path);
	}

	/**
	 * Normalize a MongoGridFs file to a response.
	 *
	 * @param MongoGridFSFile $file
	 * @param string          $path
	 *
	 * @return array
	 */
	protected function normalizeGridFSFile(MongoGridFSFile $file, $path = null)
	{
		$result = [
			'path' => trim($path ?: $file->getFilename(), '/'),
			'type' => 'file',
			'size' => $file->getSize(),
			'timestamp' => $file->file['uploadDate']->sec,
		];

		$result['dirname'] = Util::dirname($result['path']);

		if (isset($file->file['metadata']) && !empty($file->file['metadata']['mimetype'])) {
			$result['mimetype'] = $file->file['metadata']['mimetype'];
		}

		return $result;
	}
}

