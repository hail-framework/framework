<?php

namespace Hail\Filesystem\Adapter;

use Hail\Filesystem\Client\OneDrive as OneDriveClient;
use Hail\Filesystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use Hail\Filesystem\AdapterInterface;

/**
 * Class OneDrive
 * $ composer require guzzlehttp/guzzle:^6.1
 *
 * @package Hail\Filesystem\Adapter
 */
class OneDrive implements AdapterInterface
{
	use NotSupportingVisibilityTrait;

	/**
	 * @var OneDriveClient
	 */
	private $client;

	/**
	 * @param array $config
	 */
	public function __construct(array $config)
	{
		if (!isset($config['token'])) {
			throw new \InvalidArgumentException('Config not defined');
		}

		$this->client = new OneDriveClient(
			$config['token'],
			new \GuzzleHttp\Client()
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, array $config)
	{
		$response = $this->client->createFile($path, $contents);
		$responseContent = json_decode((string) $response->getBody());

		$result = [
			'type' => 'file',
			'path' => $path,
		];

		$this->updateMetadataFromResponseContent($result, $responseContent);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function writeStream($path, $resource, array $config)
	{
		return $this->write($path, stream_get_contents($resource), $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($path, $contents, array $config)
	{
		$response = $this->client->updateFile($path, $contents);
		$responseContent = json_decode((string) $response->getBody());

		$result = [
			'type' => 'file',
			'path' => $path,
		];
		$this->updateMetadataFromResponseContent($result, $responseContent);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function updateStream($path, $resource, array $config)
	{
		return $this->update($path, stream_get_contents($resource), $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($path, $newpath)
	{
		$response = $this->client->rename($path, $newpath);
		$responseContent = json_decode((string) $response->getBody());

		$result = [
			'type' => 'file',
			'path' => $newpath,
		];
		$this->updateMetadataFromResponseContent($result, $responseContent);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($path, $newpath)
	{
		$response = $this->client->copy($path, $newpath);
		$responseContent = json_decode((string) $response->getBody());

		$result = [
			'type' => 'file',
			'path' => $newpath,
		];
		$this->updateMetadataFromResponseContent($result, $responseContent);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path)
	{
		return $this->client->delete($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($dirname)
	{
		return $this->client->delete($dirname);
	}

	/**
	 * {@inheritdoc}
	 */
	public function createDir($dirname, array $config)
	{
		$response = $this->client->createFolder($dirname);
		$responseContent = json_decode((string) $response->getBody());

		$result = [
			'type' => 'dir',
			'path' => $dirname,
		];
		$this->updateMetadataFromResponseContent($result, $responseContent);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($path)
	{
		return $this->client->itemExists($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($path)
	{
		$response = $this->client->download($path);

		return [
			'type' => 'file',
			'path' => $path,
			'contents' => (string) $response->getBody(),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function readStream($path)
	{
		$response = $this->client->downloadStream($path);

		return [
			'type' => 'file',
			'path' => $path,
			'stream' => $response,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$response = $this->client->listChildren($directory);
		$responseContent = json_decode((string) $response->getBody());

		$items = $responseContent->value;

		$result = [];
		foreach ($items as $item) {
			$isFile = property_exists($item, 'file');
			$type = $isFile ? 'file' : 'dir';
			$path = $directory . '/' . $item->name;

			$metadata = compact('type', 'path');
			$this->updateMetadataFromResponseContent($metadata, $item);

			$result[] = $metadata;

			if ($recursive && !$isFile) {
				$result = array_merge($result, $this->listContents($path, true));
			}
		}

		return $result;
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
	public function getMimetype($path)
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
	public function getMetadata($path)
	{
		$response = $this->client->getMetadata($path);
		$responseContent = json_decode((string) $response->getBody());

		$result = [
			'type' => 'file',
			'path' => $path,
		];
		$this->updateMetadataFromResponseContent($result, $responseContent);

		return $result;
	}

	/**
	 * @param array     $metadata
	 * @param \StdClass $responseContent
	 * @throws \RuntimeException
	 */
	private function updateMetadataFromResponseContent(array &$metadata, \StdClass $responseContent)
	{
		$isFile = property_exists($responseContent, 'file');

		$metadata['timestamp'] = $this->getLastModifiedTimestampFromResponse($responseContent);
		$metadata['mimetype'] = $isFile ? $responseContent->file->mimeType : null;
		$metadata['size'] = $isFile ? $responseContent->size : null;
	}

	/**
	 * @param \StdClass $response
	 *
	 * @return null|int
	 *
	 * @throws \RuntimeException
	 */
	private function getLastModifiedTimestampFromResponse(\StdClass $response)
	{
		if (!property_exists($response, 'lastModifiedDateTime')) {
			return;
		}

		//date can be given with or without microseconds, try to parse from both formats
		$date = \DateTime::createFromFormat('Y-m-d\TH:i:s.uO', $response->lastModifiedDateTime, new \DateTimeZone('UTC'));
		if (!$date) {
			$date = \DateTime::createFromFormat('Y-m-d\TH:i:sO', $response->lastModifiedDateTime, new \DateTimeZone('UTC'));
		}

		if (!$date) {
			throw new \RuntimeException('Incorrect last modified date returned from the API.');
		}

		return $date->getTimestamp();
	}
}

