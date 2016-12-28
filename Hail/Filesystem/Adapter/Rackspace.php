<?php

namespace Hail\Filesystem\Adapter;

use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Exception\ClientErrorResponseException;
use OpenCloud\ObjectStore\Resource\Container;
use OpenCloud\ObjectStore\Resource\DataObject;
use Hail\Filesystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use Hail\Filesystem\Adapter\Polyfill\StreamedCopyTrait;
use Hail\Filesystem\Util;

/**
 * Class Rackspace
 * $ composer require rackspace/php-opencloud:dev-master
 *
 * @package Hail\Filesystem\Rackspace
 */
class Rackspace extends AbstractAdapter
{
	use StreamedCopyTrait;
	use NotSupportingVisibilityTrait;

	/**
	 * @var Container
	 */
	protected $container;

	/**
	 * @var string
	 */
	protected $prefix;

	/**
	 * Constructor.
	 *
	 * @param array $config
	 * @throws \InvalidArgumentException
	 */
	public function __construct(array $config)
	{
		if (!isset(
			$config['url'],
			$config['username'],
			$config['apiKey'],
			$config['region']
		)) {
			throw new \InvalidArgumentException('Config not defined!');
		}

		$client = new Rackspace($config['url'], array(
			'username' => $config['username'],
			'apiKey'   => $config['apiKey']
		));

		$objectStoreService = $client->objectStoreService($config['name'] ?? null, $config['region']);
		$container = $objectStoreService->createContainer($config['container'] ?? 'HailFS');

		//Container $container, $prefix = null
		$this->setPathPrefix($config['prefix'] ?? null);

		$this->container = $container;
	}

	/**
	 * Get the container.
	 *
	 * @return Container
	 */
	public function getContainer()
	{
		return $this->container;
	}

	/**
	 * Get an object.
	 *
	 * @param string $path
	 *
	 * @return DataObject
	 */
	protected function getObject($path)
	{
		$location = $this->applyPathPrefix($path);

		return $this->container->getObject($location);
	}

	/**
	 * Get the metadata of an object.
	 *
	 * @param string $path
	 *
	 * @return DataObject
	 */
	protected function getPartialObject($path)
	{
		$location = $this->applyPathPrefix($path);

		return $this->container->getPartialObject($location);
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, array $config)
	{
		$location = $this->applyPathPrefix($path);
		$headers = [];

		if (isset($config['headers'])) {
			$headers = $config['headers'];
		}

		$response = $this->container->uploadObject($location, $contents, $headers);

		return $this->normalizeObject($response);
	}

	/**
	 * {@inheritdoc}
	 */
	public function update($path, $contents, array $config)
	{
		$object = $this->getObject($path);
		$object->setContent($contents);
		$object->setEtag(null);
		$response = $object->update();

		if (!$response->getLastModified()) {
			return false;
		}

		return $this->normalizeObject($response);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($path, $newpath)
	{
		$object = $this->getObject($path);
		$newlocation = $this->applyPathPrefix($newpath);
		$destination = '/' . $this->container->getName() . '/' . ltrim($newlocation, '/');
		$response = $object->copy($destination);

		if ($response->getStatusCode() !== 201) {
			return false;
		}

		$object->delete();

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete($path)
	{
		try {
			$location = $this->applyPathPrefix($path);

			$this->container->deleteObject($location);
		} catch (BadResponseException $exception) {
			return false;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($dirname)
	{
		$paths = [];
		$prefix = '/' . $this->container->getName() . '/';
		$location = $this->applyPathPrefix($dirname);
		$objects = $this->container->objectList(['prefix' => $location]);

		foreach ($objects as $object) {
			$paths[] = $prefix . ltrim($object->getName(), '/');
		}

		$service = $this->container->getService();
		$response = $service->bulkDelete($paths);

		if ($response->getStatusCode() === 200) {
			return true;
		}

		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function createDir($dirname, array $config)
	{
		$headers = $config['headers'] ?? [];
		$headers['Content-Type'] = 'application/directory';
		$config = ['headers' => $headers] + $config;

		return $this->write($dirname, '', $config);
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
	public function updateStream($path, $resource, array $config)
	{
		return $this->update($path, $resource, $config);
	}

	/**
	 * {@inheritdoc}
	 */
	public function has($path)
	{
		try {
			$location = $this->applyPathPrefix($path);
			$exists = $this->container->objectExists($location);
		} catch (ClientErrorResponseException $e) {
			return false;
		}

		return $exists;
	}

	/**
	 * {@inheritdoc}
	 */
	public function read($path)
	{
		$object = $this->getObject($path);
		$data = $this->normalizeObject($object);
		$data['contents'] = (string) $object->getContent();

		return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function readStream($path)
	{
		$object = $this->getObject($path);
		$data = $this->normalizeObject($object);
		$responseBody = $object->getContent();
		$responseBody->rewind();
		$data['stream'] = $responseBody->getStream();
		$responseBody->detachStream();

		return $data;
	}

	/**
	 * {@inheritdoc}
	 */
	public function listContents($directory = '', $recursive = false)
	{
		$response = [];
		$marker = null;
		$location = $this->applyPathPrefix($directory);

		while (true) {
			$objectList = $this->container->objectList(['prefix' => $location, 'marker' => $marker]);

			if ($objectList->count() === 0) {
				break;
			}

			$response = array_merge($response, iterator_to_array($objectList));
			$marker = end($response)->getName();
		}

		return Util::emulateDirectories(array_map([$this, 'normalizeObject'], $response));
	}

	/**
	 * {@inheritdoc}
	 */
	protected function normalizeObject(DataObject $object)
	{
		$name = $object->getName();
		$name = $this->removePathPrefix($name);
		$mimetype = explode('; ', $object->getContentType());

		return [
			'type' => ((in_array('application/directory', $mimetype)) ? 'dir' : 'file'),
			'dirname' => Util::dirname($name),
			'path' => $name,
			'timestamp' => strtotime($object->getLastModified()),
			'mimetype' => reset($mimetype),
			'size' => $object->getContentLength(),
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata($path)
	{
		$object = $this->getPartialObject($path);

		return $this->normalizeObject($object);
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
	 * @param string $path
	 *
	 * @return string
	 */
	public function applyPathPrefix($path)
	{
		$encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));

		return parent::applyPathPrefix($encodedPath);
	}


}

