<?php
namespace Hail\Filesystem\Adapter;

use Hail\Filesystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use Hail\Filesystem\Adapter\Polyfill\StreamedReadingTrait;
use Hail\Filesystem\Adapter\Polyfill\StreamedWritingTrait;

use Qiniu\Auth;
use Qiniu\Processing\Operation;
use Qiniu\Processing\PersistentFop;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;

/**
 * Class Qiniu
 * $ composer require qiniu/php-sdk:~7.0
 *
 * @package Hail\Filesystem\Adapter
 */
class Qiniu extends AbstractAdapter
{
	use NotSupportingVisibilityTrait,
		StreamedWritingTrait,
		StreamedReadingTrait;

	private $bucket;
	private $auth;
	private $token;
	private $uploadManager;
	private $bucketManager;

	public function __construct(array $config)
	{
		if (!isset($config['accessKey'], $config['secretKey'], $config['domain'], $config['bucket'])) {
			throw new \InvalidArgumentException('Config not defined');
		}

		$this->bucket = $config['bucket'];
		$this->setPathPrefix($config['domain']);

		$this->auth = new Auth($config['accessKey'], $config['secretKey']);
	}

	/**
	 * 获取上传TOKEN
	 *
	 * @return string
	 */
	private function getToken()
	{
		return $this->token ?? ($this->token = $this->auth->uploadToken($this->bucket));
	}

	/**
	 * @return UploadManager
	 */
	private function getUploadManager()
	{
		return $this->uploadManager ?? ($this->uploadManager = new UploadManager());
	}

	/**
	 * @return BucketManager
	 */
	private function getBucketManager()
	{
		return $this->bucketManager ?? ($this->bucketManager = new BucketManager($this->auth));
	}

	/**
	 * 返回处理
	 *
	 * @param      $ret
	 * @param      $error
	 * @param null $key
	 *
	 * @return bool
	 */
	private function returnDeal($ret, $error, $key = null)
	{
		if ($error !== null) {
			$this->logError($error);

			return false;
		}

		if ($key !== null && isset($ret[$key])) {
			return $ret[$key];
		}

		return $ret;
	}

	public function update($path, $contents, array $config)
	{
		return $this->write($path, $contents, $config);
	}

	/**
	 * 重命名
	 *
	 * @param string $path
	 * @param string $newpath
	 *
	 * @return bool
	 */
	public function rename($path, $newpath)
	{
		$bucketMgr = $this->getBucketManager();
		list($ret, $error) = $bucketMgr->move($this->bucket, $path, $this->bucket, $newpath);

		return $this->returnDeal($ret, $error);
	}

	public function write($path, $contents, array $config, $isPutFile = false)
	{
		$token = $this->getToken();
		$params = $config['params'] ?? null;
		$mime = $config['mime'] ?? 'application/octet-stream';
		$checkCrc = $config['checkCrc'] ?? false;

		$uploadMgr = $this->getUploadManager();
		if ($isPutFile) {
			list($ret, $error) = $uploadMgr->putFile($token, $path, $contents, $params, $mime, $checkCrc);
		} else {
			list($ret, $error) = $uploadMgr->put($token, $path, $contents, $params, $mime, $checkCrc);
		}

		return $this->returnDeal($ret, $error);
	}

	public function copy($path, $newpath)
	{
		$bucketMgr = $this->getBucketManager();
		list($ret, $error) = $bucketMgr->copy($this->bucket, $path, $this->bucket, $newpath);

		return $this->returnDeal($ret, $error);
	}

	public function delete($path)
	{
		$bucketMgr = $this->getBucketManager();
		list($ret, $error) = $bucketMgr->delete($this->bucket, $path);
		$this->returnDeal($ret, $error);
	}

	public function deleteDir($dirname)
	{
		$files = $this->listContents($dirname);
		foreach ($files as $file) {
			$this->delete($file['path']);
		}

		return true;
	}

	public function read($path)
	{
		$location = $this->applyPathPrefix($path);
		return array('contents' => file_get_contents($location));
	}


	public function createDir($dirname, array $config)
	{
		$this->write($dirname, '', $config);
		return ['path' => $dirname];
	}

	public function has($path)
	{
		$meta = $this->getMetadata($path);
		if ($meta) {
			return true;
		}

		return false;
	}

	public function listContents($directory = '', $recursive = false)
	{
		$bucketMgr = $this->getBucketManager();
		list($items, $marker, $error) = $bucketMgr->listFiles($this->bucket, $directory);
		if ($error !== null) {
			$this->logError($error);

			return [];
		} else {
			$contents = [];
			foreach ($items as $item) {
				$normalized = ['type' => 'file', 'path' => $item['key'], 'timestamp' => $item['putTime']];
				if ($normalized['type'] === 'file') {
					$normalized['size'] = $item['fsize'];
				}
				$contents[] = $normalized;
			}

			return $contents;
		}
	}

	public function getMetadata($path)
	{
		$bucketMgr = $this->getBucketManager();
		list($ret, $error) = $bucketMgr->stat($this->bucket, $path);

		return $this->returnDeal($ret, $error);
	}

	public function getSize($path)
	{
		$stat = $this->getMetadata($path);
		if ($stat) {
			return ['size' => $stat['fsize']];
		}

		return false;
	}

	public function getMimetype($path)
	{
		$stat = $this->getMetadata($path);
		if ($stat) {
			return ['mimetype' => $stat['mimeType']];
		}

		return false;
	}

	public function getTimestamp($path)
	{
		$stat = $this->getMetadata($path);
		if ($stat) {
			return ['timestamp' => $stat['putTime']];
		}

		return false;
	}

	protected function logError($error)
	{

	}
}