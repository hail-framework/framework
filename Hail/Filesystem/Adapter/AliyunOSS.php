<?php

namespace Hail\Filesystem\Adapter;

use OSS\OssClient;
use OSS\Core\OssException;
use Hail\Filesystem\AdapterInterface;
use Hail\Filesystem\Util;


/**
 * Class AliyunOSS
 * $ composer require aliyuncs/oss-sdk-php:~2.0
 *
 * @package Hail\Filesystem\Adapter
 */
class AliyunOSS extends AbstractAdapter
{
	/**
	 * @var array
	 */
	protected static $resultMap = [
		'Body' => 'raw_contents',
		'Content-Length' => 'size',
		'ContentType' => 'mimetype',
		'Size' => 'size',
		'StorageClass' => 'storage_class',
	];

	/**
	 * @var array
	 */
	protected static $metaOptions = [
		'CacheControl',
		'Expires',
		'ServerSideEncryption',
		'Metadata',
		'ACL',
		'ContentType',
		'ContentDisposition',
		'ContentLanguage',
		'ContentEncoding',
	];

	protected static $metaMap = [
		'CacheControl' => OssClient::OSS_CACHE_CONTROL,
		'Expires' => OssClient::OSS_EXPIRES,
		'ServerSideEncryption' => 'x-oss-server-side-encryption',
		'Metadata' => 'x-oss-metadata-directive',
		'ACL' => OssClient::OSS_OBJECT_ACL,
		'ContentType' => OssClient::OSS_CONTENT_TYPE,
		'ContentDisposition' => OssClient::OSS_CONTENT_DISPOSTION,
		'ContentLanguage' => 'response-content-language',
		'ContentEncoding' => 'Content-Encoding',
	];


	/**
	 * @var string bucket name
	 */
	protected $bucket;

	/**
	 * @var OssClient Aliyun Oss Client
	 */
	protected $client;

	/**
	 * @var array default options[
	 *            Multipart=128 Mb - After what size should multipart be used
	 *            ]
	 */
	protected $options = [
		'Multipart' => 128,
	];

	public function __construct(array $config)
	{
		if (!isset($config['accessId'], $config['accessKey'], $config['endPoint'], $config['bucket'])) {
			throw new \InvalidArgumentException('Config not defined');
		}

		$client = new OssClient($config['accessId'], $config['accessKey'], $config['endPoint']);

		$this->client = $client;
		$this->bucket = $config['bucket'];

		try {
			if (!$this->client->doesBucketExist($this->bucket)) {
				$this->client->createBucket($this->bucket, OssClient::OSS_ACL_TYPE_PRIVATE);
			}
		} catch (OssException $e) {
			throw new \RuntimeException('Create Bucket failed: ' . $e->getMessage());
		}

		$this->setPathPrefix($config['$prefix'] ?? null);
		$this->options = array_merge($this->options, $config['options'] ?? []);
	}

	/**
	 * Get the OssClient bucket.
	 *
	 * @return string
	 */
	public function getBucket()
	{
		return $this->bucket;
	}

	/**
	 * Get the OssClient instance.
	 *
	 * @return OssClient
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

		return $this->client->doesObjectExist($this->bucket, $location);
	}

	/**
	 * {@inheritdoc}
	 */
	public function write($path, $contents, array $config)
	{
		$bucket = $this->bucket;
		$options = $this->getOptions($this->options, $config);

		try {
			$this->client->putObject($bucket, $path, $contents, $options);
		} catch (OssException $e) {
			throw new \RuntimeException('Write failed: ' . $e->getMessage());
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function writeStream($path, $resource, array $config)
	{
		$bucket = $this->bucket;
		$options = $this->getOptions($this->options, $config);

		$multipartLimit = $this->mbToBytes($options['Multipart']);
		$size = Util::getStreamSize($resource);
		$contents = fread($resource, $size);

		if ($size > $multipartLimit) {
			throw new \RuntimeException('Stream over Limit');
		} else {
			try {
				$this->client->putObject($bucket, $path, $contents, $options);
			} catch (OssException $e) {
				throw new \RuntimeException('Stream failed: ' . $e->getMessage());
			}
		}

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
	public function read($path)
	{
		$result = $this->readObject($path);
		$result['contents'] = (string) $result['raw_contents'];
		unset($result['raw_contents']);

		return $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function readStream($path)
	{
		$result = $this->readObject($path);
		$result['stream'] = $result['raw_contents'];
		rewind($result['stream']);
		// Ensure the EntityBody object destruction doesn't close the stream
		$result['raw_contents']->detachStream();
		unset($result['raw_contents']);

		return $result;
	}

	/**
	 * Read an object from the OssClient.
	 *
	 * @param string $path
	 *
	 * @return array
	 */
	protected function readObject($path)
	{
		$options = [];
		$bucket = $this->bucket;
		$object = $path;

		$result = $this->client->getObject($bucket, $object, $options);

		return $this->normalizeResponse($result);
	}

	/**
	 * {@inheritdoc}
	 */
	public function rename($path, $newpath)
	{
		$this->copy($path, $newpath);
		$this->delete($path);

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function copy($path, $newpath, $options = null)
	{
		$bucket = $this->bucket;

		try {
			$this->client->copyObject($bucket, $path, $bucket, $newpath, $options = null);
		} catch (OssException $e) {
			throw new \RuntimeException('Copy failed: ' . $e->getMessage());
		}

		return true;
	}

	/**
	 * 修改Object Meta
	 * 利用copyObject接口的特性：当目的object和源object完全相同时，表示修改object的meta信息
	 *
	 * @param OssClient $ossClient OssClient实例
	 * @param string    $bucket    存储空间名称
	 *
	 * @return null
	 */
	public function modifyMetaForObject($path, $options = null)
	{
		$bucket = $this->bucket;
		$fromBucket = $toBucket = $bucket;
		$fromObject = $toObject = $path;

		$copyOptions = $this->getOptions($options);
		try {
			$this->client->copyObject($fromBucket, $fromObject, $toBucket, $toObject, $copyOptions);
		} catch (OssException $e) {
			throw new \RuntimeException('Modify Meta failed: ' . $e->getMessage());
		}

		// print(__FUNCTION__ . ": OK" . "\n");
		return true;
	}


	/**
	 * {@inheritdoc}
	 */
	public function delete($path)
	{
		$bucket = $this->bucket;
		$object = $path;

		try {
			$this->client->deleteObject($bucket, $object);
		} catch (OssException $e) {
			throw new \RuntimeException('Delete failed: ' . $e->getMessage());
		}

		return !$this->has($path);
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteDir($path)
	{
		$path = rtrim($this->applyPathPrefix($path), '/') . '/';

		$bucket = $this->bucket;
		$dir = $this->listDirObjects($path, true);

		if (count($dir['objects']) > 0) {
			foreach ($dir['objects'] as $object) {
				$objects[] = $object['Key'];
			}

			try {
				$this->client->deleteObjects($bucket, $objects);
			} catch (OssException $e) {
				throw new \RuntimeException('Delete dir failed: ' . $e->getMessage());
			}

		}

		try {
			$this->client->deleteObject($bucket, $path);
		} catch (OssException $e) {
			throw new \RuntimeException('Delete dir failed: ' . $e->getMessage());
		}

		return true;
	}


	/**
	 * 列举文件夹内文件列表；可递归获取子文件夹；
	 */
	public function listDirObjects($dirname = '', $recursive = false)
	{
		$bucket = $this->bucket;

		$delimiter = '/';
		$nextMarker = '';
		$maxkeys = 1000;

		$options = [
			'delimiter' => $delimiter,
			'prefix' => $dirname,
			'max-keys' => $maxkeys,
			'marker' => $nextMarker,
		];

		try {
			$listObjectInfo = $this->client->listObjects($bucket, $options);
		} catch (OssException $e) {
			throw new \RuntimeException('List dir failed: ' . $e->getMessage());
		}

		// var_dump($listObjectInfo);
		$objectList = $listObjectInfo->getObjectList(); // 文件列表
		$prefixList = $listObjectInfo->getPrefixList(); // 目录列表

		if (!empty($objectList)) {
			foreach ($objectList as $objectInfo) {
				$object['Prefix'] = $dirname;
				$object['Key'] = $objectInfo->getKey();
				$object['LastModified'] = $objectInfo->getLastModified();
				$object['eTag'] = $objectInfo->getETag();
				$object['Type'] = $objectInfo->getType();
				$object['Size'] = $objectInfo->getSize();
				$object['StorageClass'] = $objectInfo->getStorageClass();

				$dir['objects'][] = $object;
			}
		} else {
			$dir['objects'] = [];
		}

		if (!empty($prefixList)) {
			foreach ($prefixList as $prefixInfo) {
				$dir['prefix'][] = $prefixInfo->getPrefix();
			}
		} else {
			$dir['prefix'] = [];
		}


		if ($recursive) {

			foreach ($dir['prefix'] as $pfix) {
				$next = $this->listDirObjects($pfix, $recursive);

				$dir['objects'] = array_merge($dir['objects'], $next['objects']);
			}

		}

		return $dir;
	}

	/**
	 * {@inheritdoc}
	 */
	public function createDir($path, array $config)
	{
		$bucket = $this->bucket;

		try {
			$this->client->createObjectDir($bucket, $path);
		} catch (OssException $e) {
			throw new \RuntimeException('Create dir failed: ' . $e->getMessage());
		}

		return ['path' => $path, 'type' => 'dir'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMetadata($path)
	{
		$bucket = $this->bucket;
		$object = $path;

		try {
			$objectMeta = $this->client->getObjectMeta($bucket, $object);
		} catch (OssException $e) {
			throw new \RuntimeException('Get Meta failed: ' . $e->getMessage());
		}

		return $objectMeta;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getMimetype($path)
	{
		$object = $this->getMetadata($path);
		$object['mimetype'] = $object['content-type'];

		return $object;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSize($path)
	{
		$object = $this->getMetadata($path);
		$object['size'] = $object['content-length'];

		return $object;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getTimestamp($path)
	{
		$object = $this->getMetadata($path);
		$object['timestamp'] = $object['date'];

		return $object;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getVisibility($path)
	{
		try {
			$visibility = $this->client->getObjectAcl($this->bucket, $path);
			if ($visibility === 'default') {
				$visibility = $this->client->getBucketAcl($this->bucket);
			}

			$visibility = strpos($visibility, 'public') !== false ?
				AdapterInterface::VISIBILITY_PUBLIC :
				AdapterInterface::VISIBILITY_PRIVATE;
		} catch (OssException $e) {
			throw new \RuntimeException('Get Visibility failed: ' . $e->getMessage());
		}

		return compact('visibility');
	}

	/**
	 * The the ACL visibility.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	protected function getObjectACL($path)
	{
		$metadata = $this->getVisibility($path);

		return $metadata['visibility'] === AdapterInterface::VISIBILITY_PUBLIC ?
			OssClient::OSS_ACL_TYPE_PUBLIC_READ :
			OssClient::OSS_ACL_TYPE_PRIVATE;
	}

	/**
	 * {@inheritdoc}
	 */
	public function setVisibility($path, $visibility)
	{
		$acl = ($visibility === AdapterInterface::VISIBILITY_PUBLIC) ?
			OssClient::OSS_ACL_TYPE_PUBLIC_READ :
			OssClient::OSS_ACL_TYPE_PRIVATE;

		$this->client->putObjectAcl($this->bucket, $path, $acl);

		return compact('visibility');
	}

	/**
	 * {@inheritdoc}
	 */
	public function listContents($dirname = '', $recursive = false)
	{
		$dir = $this->listDirObjects($dirname, true);
		$contents = $dir['objects'];

		$result = array_map([$this, 'normalizeResponseOri'], $contents);
		$result = array_filter($result, function ($value) {
			return $value['path'] !== false;
		});

		return Util::emulateDirectories($result);
	}

	/**
	 * Normalize a result from AWS.
	 *
	 * @param array  $object
	 * @param string $path
	 *
	 * @return array file metadata
	 */
	protected function normalizeResponseOri(array $object, $path = null)
	{
		$result = ['path' => $path ?: $this->removePathPrefix($object['Key'] ?? $object['Prefix'])];
		$result['dirname'] = Util::dirname($result['path']);

		if (isset($object['LastModified'])) {
			$result['timestamp'] = strtotime($object['LastModified']);
		}

		if (substr($result['path'], -1) === '/') {
			$result['type'] = 'dir';
			$result['path'] = rtrim($result['path'], '/');

			return $result;
		}

		$result = array_merge($result, Util::map($object, static::$resultMap), ['type' => 'file']);

		return $result;
	}

	/**
	 * Normalize a result from AWS.
	 *
	 * @param array  $object
	 * @param string $path
	 *
	 * @return array file metadata
	 */
	protected function normalizeResponse($content)
	{
		$result['raw_contents'] = $content;
		$result = array_merge($result, ['type' => 'file']);

		return $result;
	}

	/**
	 * Get options for a OSS call. done
	 *
	 * @param array $options
	 * @param array $config
	 *
	 * @return array OSS options
	 */
	protected function getOptions(array $options = [], array $config = null)
	{
		$options += $this->options;

		if ($config) {
			$options = array_merge($options, $this->getOptionsFromConfig($config));
		}

		return [OssClient::OSS_HEADERS => $options];
	}

	/**
	 * Retrieve options from a Config instance. done
	 *
	 * @param array $config
	 *
	 * @return array
	 */
	protected function getOptionsFromConfig(array $config)
	{
		$options = [];

		foreach (static::$metaOptions as $option) {
			if (!isset($config[$option])) {
				continue;
			}
			$options[static::$metaMap[$option]] = $config[$option];
		}

		if ($visibility = $config['visibility'] ?? null) {
			$options[OssClient::OSS_OBJECT_ACL] = $visibility === AdapterInterface::VISIBILITY_PUBLIC ?
				OssClient::OSS_ACL_TYPE_PUBLIC_READ :
				OssClient::OSS_ACL_TYPE_PRIVATE;
		}

		if ($mimetype = $config['mimetype'] ?? null) {
			$options[OssClient::OSS_CONTENT_TYPE] = $mimetype;
		}

		return $options;
	}

	/**
	 * Convert megabytes to bytes.
	 *
	 * @param int $megabytes
	 *
	 * @return int
	 */
	protected function mbToBytes($megabytes)
	{
		return $megabytes * 1024 * 1024;
	}

}