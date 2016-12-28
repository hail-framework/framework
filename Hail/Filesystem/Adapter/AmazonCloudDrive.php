<?php

namespace Hail\Filesystem\Adapter;

use CloudDrive\Cache\SQLite;
use CloudDrive\CloudDrive;
use CloudDrive\Node;
use Hail\Filesystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use Hail\Filesystem\Adapter\Polyfill\StreamedCopyTrait;

/**
 * Class AmazonCloudDrive
 * $ composer require alex-phillips/clouddrive:dev-master
 *
 * @package Hail\Filesystem\Adapter
 */
class AmazonCloudDrive extends AbstractAdapter
{

	use StreamedCopyTrait;
	use NotSupportingVisibilityTrait;

	/**
	 * The Cloud Drive instance.
	 *
	 * @var CloudDrive
	 */
	private $cloudDrive;

	/**
	 * AmazonCloudDrive constructor.
	 *  [
	 *      'email' =>
	 *      'clientId' =>
	 *      'clientSecret' =>
	 *      'cacheDir' =>
	 *  ]
	 *
	 * @param array $config
	 *
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 */
	public function __construct(array $config)
	{
		if (!isset(
			$config['email'],
			$config['clientId'],
			$config['clientSecret'],
			$config['cacheDir']
		)
		) {
			throw new \InvalidArgumentException('Some config not defined');
		}

		$cache = new SQLite($config['email'], $config['cacheDir']);
		$drive = new CloudDrive($config['email'], $config['clientId'], $config['clientSecret'], $cache);

		$response = $drive->getAccount()->authorize();

		if (!$response['success']) {
			if (isset($response['data']['auth_url'])) {
				$response = $drive->getAccount()->authorize($response['data']['auth_url']);
			}

			if (!$response['success']) {
				throw new \RuntimeException('CloudDrive authorize failure');
			}
		}

		// Initialize Node
		Node::init($drive->getAccount(), $cache);

		// Sync your local cache with the current state of your Cloud Drive.
		$drive->getAccount()->sync();

		$this->cloudDrive = $drive;
	}

	/**
	 * Write a new file.
	 *
	 * @param string $path
	 * @param string $contents
	 * @param array  $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function write($path, $contents, array $config)
	{
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $contents);
		fseek($stream, 0);

		$res = $this->cloudDrive->uploadStream($stream, $path);

		return $res['success'];
	}

	/**
	 * Write a new file using a stream.
	 *
	 * @param string   $path
	 * @param resource $resource
	 * @param array    $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function writeStream($path, $resource, array $config)
	{
		$res = $this->cloudDrive->uploadStream($resource, $path);

		return $res['success'];
	}

	/**
	 * Update a file.
	 *
	 * @param string $path
	 * @param string $contents
	 * @param array  $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function update($path, $contents, array $config)
	{
		$stream = fopen('php://memory', 'r+');
		fwrite($stream, $contents);
		fseek($stream, 0);

		$res = $this->cloudDrive->uploadStream($stream, $path, true);

		return $res['success'];
	}

	/**
	 * Update a file using a stream.
	 *
	 * @param string   $path
	 * @param resource $resource
	 * @param array    $config Config object
	 *
	 * @return array|false false on failure file meta data on success
	 */
	public function updateStream($path, $resource, array $config)
	{
		$res = $this->cloudDrive->uploadStream($resource, $path, true);

		return $res['success'];
	}

	/**
	 * Rename a file.
	 *
	 * @param string $path
	 * @param string $newpath
	 *
	 * @return bool
	 */
	public function rename($path, $newpath)
	{
		if (!($node = Node::loadByPath($path))) {
			return false;
		}

		$res = $node->rename($newpath);

		return $res['success'];
	}

	/**
	 * Delete a file.
	 *
	 * @param string $path
	 *
	 * @return bool
	 */
	public function delete($path)
	{
		if (!($node = Node::loadByPath($path))) {
			return false;
		}

		if (!$node->isFile()) {
			return false;
		}

		// TODO full delete instead of just trashed

		$res = $node->trash();

		return $res['success'];
	}

	/**
	 * Delete a directory.
	 *
	 * @param string $dirname
	 *
	 * @return bool
	 */
	public function deleteDir($dirname)
	{
		if (!($node = Node::loadByPath($dirname))) {
			return false;
		}

		if (!$node->isFolder()) {
			return false;
		}

		$res = $node->trash();

		return $res['success'];
	}

	/**
	 * Create a directory.
	 *
	 * @param string $dirname directory name
	 * @param array  $config
	 *
	 * @return array|false
	 */
	public function createDir($dirname, array $config)
	{
		$result = $this->cloudDrive->createDirectoryPath($dirname);

		return $result['success'];
	}

	/**
	 * Check whether a file exists.
	 *
	 * @param string $path
	 *
	 * @return array|bool|null
	 */
	public function has($path)
	{
		$res = $this->cloudDrive->nodeExists($path);

		return $res['success'];
	}

	/**
	 * Read a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function read($path)
	{
		$stream = $this->readStream($path);

		if ($stream) {
			$stream = $stream['stream'];

			$contents = stream_get_contents($stream);

			fclose($stream);

			return ['contents' => $contents];
		}

		return false;
	}

	/**
	 * Read a file as a stream.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function readStream($path)
	{
		if (!($node = Node::loadByPath($path))) {
			return false;
		}

		$res = $node->download();

		return $res['success'] ? $res['data'] : false;
	}

	/**
	 * List contents of a directory.
	 *
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return array
	 */
	public function listContents($directory = '', $recursive = false)
	{
		if (!($node = Node::loadByPath($directory))) {
			return false;
		}

		if (!$node->isFolder()) {
			return false;
		}

		return $this->nodeToArray($node, [], $recursive, $directory);
	}

	/**
	 * Transform a node into an array of file info arrays.
	 *
	 * @param Node  $parent
	 * @param array $output
	 * @param       $recursive
	 * @param       $prefix
	 *
	 * @return array
	 */
	private function nodeToArray(Node $parent, array $output, $recursive, $prefix)
	{
		foreach ($parent->getChildren() as $node) {
			if ($node->inTrash()) {
				continue;
			}

			$entry = [
				'path' => $this->removePathPrefix(trim($prefix . '/' . $node['name'], '/')),
				'basename' => $node['name'],
				'type' => $node->isFolder() ? 'dir' : 'file',
				'timestamp' => (new \DateTime($node['modifiedDate']))->getTimestamp(),
			];

			if (isset($node['contentProperties'])) {
				$entry = array_merge($entry, $node['contentProperties']);
			}

			$output[] = $entry;

			if ($node->isFolder() && $recursive) {
				$output = $this->nodeToArray($node, $output, $recursive, rtrim($prefix, '/') . '/' . $node['name']);
			}
		}

		return $output;
	}

	/**
	 * Get all the meta data of a file or directory.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getMetadata($path)
	{
		if (!($node = Node::loadByPath($path))) {
			return false;
		}

		return $node->getMetadata();
	}

	/**
	 * Get all the meta data of a file or directory.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getSize($path)
	{
		if (!($node = Node::loadByPath($path))) {
			return false;
		}

		return $node['contentProperties'];
	}

	/**
	 * Get the mimetype of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getMimetype($path)
	{
		if (!($node = Node::loadByPath($path))) {
			return false;
		}

		return ['mimetype' => $node['contentProperties']['contentType']];
	}

	/**
	 * Get the timestamp of a file.
	 *
	 * @param string $path
	 *
	 * @return array|false
	 */
	public function getTimestamp($path)
	{
		if (!($node = Node::loadByPath($path))) {
			return false;
		}

		return ['timestamp' => (new \DateTime($node['modifiedDate']))->getTimestamp()];
	}
}

