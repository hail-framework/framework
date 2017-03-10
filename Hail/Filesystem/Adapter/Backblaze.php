<?php

namespace Hail\Filesystem\Adapter;

use ChrisWhite\B2\Client;
use Hail\Filesystem\Adapter\Polyfill\NotSupportingVisibilityTrait;

/**
 * Class Backblaze
 * $ composer require cwhite92/b2-sdk-php:^1.2
 *
 * @package Hail\Filesystem\Adapter
 */
class Backblaze extends AbstractAdapter
{
	use NotSupportingVisibilityTrait;

	protected $client;
	protected $bucketName;

	public function __construct(array $config)
	{
		if (!isset($config['accountId'], $config['applicationKey'], $config['bucketName'])) {
			throw new \InvalidArgumentException('AwsS3 config not defined!');
		}

		$this->client = new Client($config['accountId'], $config['applicationKey']);
		$this->bucketName = $config['bucketName'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($path)
	{
		return $this->getClient()->fileExists(['FileName' => $path, 'BucketName' => $this->bucketName]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, array $config)
	{
		$file = $this->getClient()->upload([
			'BucketName' => $this->bucketName,
			'FileName' => $path,
			'Body' => $contents,
		]);

		return $this->getFileInfo($file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function writeStream($path, $resource, array $config)
	{
		$file = $this->getClient()->upload([
			'BucketName' => $this->bucketName,
			'FileName' => $path,
			'Body' => $resource,
		]);

		return $this->getFileInfo($file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($path, $contents, array $config)
	{
		$file = $this->getClient()->upload([
			'BucketName' => $this->bucketName,
			'FileName' => $path,
			'Body' => $contents,
		]);

		return $this->getFileInfo($file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateStream($path, $resource, array $config)
	{
		$file = $this->getClient()->upload([
			'BucketName' => $this->bucketName,
			'FileName' => $path,
			'Body' => $resource,
		]);

		return $this->getFileInfo($file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($path)
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function readStream($path)
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($path, $newpath)
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($path, $newPath)
	{
		return $this->getClient()->upload([
			'BucketName' => $this->bucketName,
			'FileName' => $newPath,
			'Body' => @file_get_contents($path),
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path)
	{
		return $this->getClient()->deleteFile(['FileName' => $path, 'BucketName' => $this->bucketName]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($path)
	{
		return $this->getClient()->deleteFile(['FileName' => $path, 'BucketName' => $this->bucketName]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createDir($path, array $config)
	{
		return $this->getClient()->upload([
			'BucketName' => $this->bucketName,
			'FileName' => $path,
			'Body' => '',
		]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata($path)
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMimetype($path)
	{
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSize($path)
	{
		$file = $this->getClient()->getFile(['FileName' => $path, 'BucketName' => $this->bucketName]);

		return $this->getFileInfo($file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTimestamp($path)
	{
		$file = $this->getClient()->getFile(['FileName' => $path, 'BucketName' => $this->bucketName]);

		return $this->getFileInfo($file);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getClient()
	{
		return $this->client;
	}

	/**
	 * {@inheritdoc}
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$fileObjects = $this->getClient()->listFiles([
			'BucketName' => $this->bucketName,
		]);
		$result = [];
		foreach ($fileObjects as $fileObject) {
			$result[] = $this->getFileInfo($fileObject);
		}

		return $result;
	}

	/**
	 * Get file info
	 *
	 * @param $file
	 *
	 * @return array
	 */
	protected function getFileInfo($file)
	{
		$normalized = [
			'type' => 'file',
			'path' => $file->getName(),
			'timestamp' => substr($file->getUploadTimestamp(), 0, -3),
			'size' => $file->getSize(),
		];

		return $normalized;
	}
}