<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2017/2/13 0013
 * Time: 15:32
 */

namespace Hail\Filesystem\Adapter;

use Hail\Filesystem\AdapterInterface;
use SCS;
use SCSException;
use Hail\Filesystem\Util;


/**
 * Class Adapter
 * $ composer require cloudmario/scs:dev-master
 *
 * @package Hail\Filesystem\Adapter
 */
class SinaSCS extends AbstractAdapter
{
	/**
	 * @var array
	 */
	protected static $resultMap = [
		'Size' => 'size',
		'Type' => 'mimetype',
		'ContentMD5' => 'contentMd5',
		'LastModified' => 'lastModified',
		'FileMeta' => 'userMetadata',
	];

	/**
	 * @var array
	 */
	protected static $metaOptions = [
		'CacheControl',
		'Expires',
		'ServerExpires',
		'Metadata',
		'ACL',
		'ContentType',
		'ContentDisposition',
		'ContentLanguage',
		'ContentEncoding',
	];

	protected static $metaMap = [
		'CacheControl' => 'Cache-Control',
		'Expires' => 'Expires',
		'ServerExpires' => 'x-sina-expire',
		'ACL' => 'acl',
		'ContentType' => 'Content-Type',
		'ContentDisposition' => 'Content-Disposition',
		'ContentEncoding' => 'Content-Encoding',
		'Metadata' => 'meta',
	];

	/**
	 *
	 * @var SCS
	 */
	protected $client;
	protected $bucket;

	public function __construct(array $config)
	{
		if (!isset($config['accessKey'], $config['secretKey'], $config['bucket'])) {
			throw new \InvalidArgumentException('Config not defined');
		}

		SCS::setAuth($config['accessKey'], $config['secretKey']);
		$this->bucket = $config['bucket'];

		if (!SCS::getMeta($this->bucket)) {
			SCS::putBucket($this->bucket);
		}
	}

	public function copy($path, $newpath)
	{
		if (!$this->has($path)) {
			return false;
		} else {
			try {
				return SCS::copyObject($this->bucket, $path, $this->bucket, $newpath) ? true : false;
			} catch (SCSException $e) {
				return false;
			}
		}
	}

	public function createDir($dirname, array $config)
	{
		$dirname = rtrim($dirname, $this->pathSeparator) . $this->pathSeparator;

		try {
			$result = SCS::putObjectString($this->bucket, $dirname, '');
			if (!$result) {
				return false;
			}
		} catch (SCSException $e) {
			return false;
		}

		return ['path' => $dirname, 'type' => 'dir'];
	}

	public function delete($path)
	{
		try {
			return SCS::deleteObject($this->bucket, $path);
		} catch (SCSException $e) {
			return false;
		}
	}

	public function deleteDir($dirname)
	{
		$objects = $this->listContents($dirname, true);
		try {
			foreach ($objects as $object) {
				SCS::deleteObject($this->bucket, $object['path']);
			}

			$dirname = rtrim($dirname, $this->pathSeparator) . $this->pathSeparator;
			SCS::deleteObject($this->bucket, $dirname);
		} catch (\Exception $e) {
			return false;
		}
	}

	public function getMetadata($path)
	{
		try {
			$result = SCS::getObjectInfo($this->bucket, $path);
		} catch (\Exception $e) {
			return false;
		}

		$result = get_object_vars($result);

		return $this->normalizeResponse($result, $path);
	}

	public function getMimetype($path)
	{
		return $this->getMetadata($path);
	}

	public function getSize($path)
	{
		return $this->getMetadata($path);
	}

	public function getTimestamp($path)
	{
		return $this->getMetadata($path);
	}

	public function setVisibility($path, $visibility)
	{
		$meta = SCS::getMeta($this->bucket);
		$owner = $meta->Owner;

		$grant = [];
		if ($visibility === AdapterInterface::VISIBILITY_PUBLIC) {
			$grant = ['read'];
		}

		$acl = [
			$owner => ['FULL_CONTROL'],
			'GRPS000000ANONYMOUSE' => $grant,
			'GRPS0000000CANONICAL' => $grant,
		];

		SCS::setAccessControlPolicy($this->bucket, $path, $acl);

		return compact('visibility');
	}

	public function getVisibility($path)
	{
		$visibility = SCS::getAccessControlPolicy($this->bucket, $path);
		if (
			(
				isset($visibility['acl']['GRPS0000000CANONICAL']) &&
				in_array('read', $visibility['acl']['GRPS0000000CANONICAL'], true)
			) || (
				isset($visibility['acl']['GRPS000000ANONYMOUSE']) &&
				in_array('read', $visibility['acl']['GRPS000000ANONYMOUSE'], true)
			)
		) {
			$visibility = AdapterInterface::VISIBILITY_PUBLIC;
		} else {
			$visibility = AdapterInterface::VISIBILITY_PRIVATE;
		}

		return compact('visibility');
	}

	public function has($path)
	{
		return SCS::getMeta($this->bucket, $path) ? true : false;
	}

	public function listContents($directory = '', $recursive = false)
	{
		$prefix = empty($directory) ? $directory : (rtrim($directory, $this->pathSeparator) . $this->pathSeparator);
		$delimiter = $recursive ? '' : '/';

		$marker = $nextMarker = null;
		$isTruncated = false;
		$result = [];

		try {
			while (true) {
				$response = SCS::getBucket($this->bucket, $prefix, $marker, null, $delimiter, true, $nextMarker, $isTruncated);

				foreach ($response as $k => $v) {
					if (isset($v['prefix'])) {
						$result[] = [
							'type' => 'dir',
							'path' => $v['prefix'],
						];
					} else {
						$result[] = [
							'timestamp' => $v['time'],
							'type' => 'file',
							'path' => $v['name'],
							'size' => $v['size'],
							'mime' => $v['type'],
						];
					}
				}

				if ($isTruncated) {
					break;
				} else {
					$marker = $nextMarker;
				}
			}
		} catch (SCSException $e) {
			return [];
		}

		return $result;
	}

	public function read($path)
	{
		try {
			$contents = SCS::getObject($this->bucket, $path);
		} catch (SCSException $e) {
			return false;
		}

		return compact('contents', 'path');
	}

	public function readStream($path)
	{
		try {
			$stream = fopen('php://memory', 'rb+');
			SCS::getObject($this->bucket, $path, $stream);
			rewind($stream);
		} catch (SCSException $e) {
			return false;
		}

		return compact('stream');
	}

	public function rename($path, $newpath)
	{
		if (!$this->copy($path, $newpath)) {
			return false;
		}

		return $this->delete($path);
	}

	public function update($path, $contents, array $config)
	{
		return $this->upload($path, $contents, $config);
	}

	public function updateStream($path, $resource, array $config)
	{
		return $this->upload($path, $resource, $config);
	}

	public function write($path, $contents, array $config)
	{
		return $this->upload($path, $contents, $config);
	}

	public function writeStream($path, $resource, array $config)
	{
		return $this->upload($path, $resource, $config);
	}

	protected function upload($path, $body, array $config)
	{
		$options = $this->getOptionsFromConfig($config);
		$response = null;

		try {
			$acl = $options['acl'] ?? SCS::ACL_PRIVATE;
			$meta = $options['meta'] ?? [];
			unset($options['acl'], $options['meta']);

			if (is_resource($body)) {
				$size = Util::getStreamSize($body);
				$body = SCS::inputResource($body, $size);
			} elseif (!is_string($body)) {
				throw new \InvalidArgumentException("$body type should be string or resource");
			}

			$response = SCS::putObject($body, $this->bucket, $path, $acl, $meta, $options);
		} catch (SCSException $e) {
			return false;
		}

		return $this->normalizeResponse($response->metadata, $path);
	}

	/**
	 * Normalize the object result array.
	 *
	 * @param array $response
	 *
	 * @return array
	 */
	protected function normalizeResponse(array $response, $path = null)
	{
		$result = ['path' => $path ?: $this->removePathPrefix(null)];

		if (isset($response['lastModified'])) {
			$result['timestamp'] = strtotime($response['lastModified']);
		}

		if (substr($result['path'], -1) === $this->pathSeparator) {
			$result['type'] = 'dir';
			$result['path'] = rtrim($result['path'], $this->pathSeparator);

			return $result;
		}

		return array_merge($result, Util::map($response, static::$resultMap), ['type' => 'file']);
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
			$options[static::$metaMap['ACL']] = $visibility === AdapterInterface::VISIBILITY_PUBLIC ?
				SCS::ACL_PUBLIC_READ :
				SCS::ACL_PRIVATE;
		}

		if ($mimetype = $config['mimetype'] ?? null) {
			$options[static::$metaMap['ContentType']] = $mimetype;
		}

		if (isset($options['meta'])) {
			$meta = [];
			foreach ($options['meta'] as $k => $v) {
				$meta['x-amz-meta-' . $k] = $v;
			}
			$options['meta'] = $meta;
		}

		return $options;
	}
}