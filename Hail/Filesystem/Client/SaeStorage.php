<?php
namespace Hail\Filesystem\Client;

//namespace sinacloud\sae;
// SAE 官方 Storage客户端 代码，非SAE环境中使用
// 本文件已经在SAE中预置，在SAE中不需要

if (defined('SAE_APPNAME')) {
	define('DEFAULT_STORAGE_ENDPOINT', 'api.i.sinas3.com:81');
	define('DEFAULT_USE_SSL', false);
} else {
	define('DEFAULT_STORAGE_ENDPOINT', 'api.sinas3.com');
	define('DEFAULT_USE_SSL', true);
}

/**
 * SAE Storage PHP客户端
 *
 * @copyright Copyright (c) 2015, SINA, All rights reserved.
 *
 * ```php
 * <?php
 * use sinacloud\sae\Storage as Storage;
 *
 * **类初始化**
 *
 * // 方法一：在SAE运行环境中时可以不传认证信息，默认会从应用的环境变量中取
 * $s = new Storage();
 *
 * // 方法二：如果不在SAE运行环境或者要连非本应用的storage，需要传入所连应用的"应用名:应用AccessKey"和"应用SecretKey"
 * $s = new Storage("$AppName:$AccessKey", $SecretKey);
 *
 * **Bucket操作**
 *
 * // 创建一个Bucket test
 * $s->putBucket("test");
 *
 * // 获取Bucket列表
 * $s->listBuckets();
 *
 * // 获取Bucket列表及Bucket中Object数量和Bucket的大小
 * $s->listBuckets(true);
 *
 * // 获取test这个Bucket中的Object对象列表，默认返回前1000个，如果需要返回大于1000个Object的列表，可以通过limit参数来指定。
 * $s->getBucket("test");
 *
 * // 获取test这个Bucket中所有以 *a/* 为前缀的Objects列表
 * $s->getBucket("test", 'a/');
 *
 * // 获取test这个Bucket中所有以 *a/* 为前缀的Objects列表，只显示 *a/N* 这个Object之后的列表（不包含 *a/N* 这个Object）。
 * $s->getBucket("test", 'a/', 'a/N');
 *
 * // Storage也可以当成一个伪文件系统来使用，比如获取 *a/* 目录下的Object（不显示其下的子目录的具体Object名称，只显示目录名）
 * $s->getBucket("test", 'a/', null, 10000, '/');
 *
 * // 删除一个空的Bucket test
 * $s->deleteBucket("test");
 *
 * **Object上传操作**
 *
 * // 把$_FILES全局变量中的缓存文件上传到test这个Bucket，设置此Object名为1.txt
 * $s->putObjectFile($_FILES['uploaded']['tmp_name'], "test", "1.txt");
 *
 * // 把$_FILES全局变量中的缓存文件上传到test这个Bucket，设置此Object名为sae/1.txt
 * $s->putObjectFile($_FILES['uploaded']['tmp_name'], "test", "sae/1.txt");
 *
 * // 上传一个字符串到test这个Bucket中，设置此Object名为string.txt，并且设置其Content-type
 * $s->putObject("This is string.", "test", "string.txt", Storage::ACL_PUBLIC_READ, array(), array('Content-Type' => 'text/plain'));
 *
 * // 上传一个文件句柄（必须是buffer或者一个文件，文件会被自动fclose掉）到test这个Bucket中，设置此Object名为file.txt
 * $s->putObject(Storage::inputResource(fopen($_FILES['uploaded']['tmp_name'], 'rb'), filesize($_FILES['uploaded']['tmp_name']), "test", "file.txt", Storage::ACL_PUBLIC_READ);
 *
 * **Object下载操作**
 *
 * // 从test这个Bucket读取Object 1.txt，输出为此次请求的详细信息，包括状态码和1.txt的内容等
 * var_dump($s->getObject("test", "1.txt"));
 *
 * // 从test这个Bucket读取Object 1.txt，把1.txt的内容保存在SAE_TMP_PATH变量指定的TmpFS中，savefile.txt为保存的文件名;SAE_TMP_PATH路径具有写权限，用户可以往这个目录下写文件，但文件的生存周期等同于PHP请求，也就是当该PHP请求完成执行时，所有写入SAE_TMP_PATH的文件都会被销毁
 * $s->getObject("test", "1.txt", SAE_TMP_PATH."savefile.txt");
 *
 * // 从test这个Bucket读取Object 1.txt，把1.txt的内容保存在打开的文件句柄中
 * $s->getObject("test", "1.txt", fopen(SAE_TMP_PATH."savefile.txt", 'wb'));
 *
 * **Object删除操作**
 *
 * // 从test这个Bucket删除Object 1.txt
 * $s->deleteObject("test", "1.txt");
 *
 * **Object复制操作**
 *
 * // 把test这个Bucket的Object 1.txt内容复制到newtest这个Bucket的Object 1.txt
 * $s->copyObject("test", "1.txt", "newtest", "1.txt");
 *
 * // 把test这个Bucket的Object 1.txt内容复制到newtest这个Bucket的Object 1.txt，并设置Object的浏览器缓存过期时间为10s和Content-Type为text/plain
 * $s->copyObject("test", "1.txt", "newtest", "1.txt", array('expires' => '10s'), array('Content-Type' => 'text/plain'));
 *
 * **生成一个外网能够访问的url**
 *
 * // 为私有Bucket test中的Object 1.txt生成一个能够在外网用GET方法临时访问的URL，次URL过期时间为600s
 * $s->getTempUrl("test", "1.txt", "GET", 600);
 *
 * // 为test这个Bucket中的Object 1.txt生成一个能用CDN访问的URL
 * $s->getCdnUrl("test", "1.txt");
 *
 * **调试模式**
 *
 * // 开启调试模式，出问题的时候方便定位问题，设置为true后遇到错误的时候会抛出异常而不是写一条warning信息到日志。
 * $s->setExceptions(true);
 * ?>
 * ```
 */
class Storage
{
	// ACL flags
	const ACL_PRIVATE = '';
	const ACL_PUBLIC_READ = '.r:*';

	private static $__accessKey = null;
	private static $__secretKey = null;
	private static $__account = null;

	/**
	 * 默认使用的分隔符，getBucket()等用到
	 *
	 * @var string
	 * @access public
	 * @static
	 */
	public static $defDelimiter = null;

	public static $endpoint = DEFAULT_STORAGE_ENDPOINT;

	public static $proxy = null;

	/**
	 * 使用SSL连接？
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	public static $useSSL = DEFAULT_USE_SSL;

	/**
	 * 是否验证SSL证书
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	public static $useSSLValidation = false;

	/**
	 * 使用的SSL版本
	 *
	 * @var const
	 * @access public
	 * @static
	 */
	public static $useSSLVersion = 1;

	/**
	 * 出现错误的时候是否使用PHP Exception（默认使用trigger_error纪录错误）
	 *
	 * @var bool
	 * @access public
	 * @static
	 */
	public static $useExceptions = false;

	/**
	 * 构造函数
	 *
	 * @param string  $accessKey 此处需要使用"应用名:应用Accesskey"
	 * @param string  $secretKey 应用Secretkey
	 * @param boolean $useSSL    是否使用SSL
	 * @param string  $endpoint  SAE Storage的endpoint
	 *
	 * @return void
	 */
	public function __construct($accessKey = null, $secretKey = null,
	                            $useSSL = DEFAULT_USE_SSL, $endpoint = DEFAULT_STORAGE_ENDPOINT)
	{
		if ($accessKey !== null && $secretKey !== null) {
			self::setAuth($accessKey, $secretKey);
		} else if (defined('SAE_APPNAME')) {
			// We are in SAE Runtime
			self::setAuth(SAE_APPNAME . ':' . SAE_ACCESSKEY, SAE_SECRETKEY);
		}
		self::$useSSL = $useSSL;
		self::$endpoint = $endpoint;
	}


	/**
	 * 设置SAE的Storage的endpoint
	 *
	 * @param string $host SAE Storage的hostname
	 *
	 * @return void
	 */
	public function setEndpoint($host)
	{
		self::$endpoint = $host;
	}


	/**
	 * 设置访问的Accesskey和Secretkey
	 *
	 * @param string $accessKey 此处需要使用"应用名:应用Accesskey"
	 * @param string $secretKey 应用Secretkey
	 *
	 * @return void
	 */
	public static function setAuth($accessKey, $secretKey)
	{
		$e = explode(':', $accessKey);
		self::$__account = $e[0];
		self::$__accessKey = $e[1];
		self::$__secretKey = $secretKey;
	}


	public static function hasAuth()
	{
		return (self::$__accessKey !== null && self::$__secretKey !== null);
	}


	/**
	 * 开启或者关闭SSL
	 *
	 * @param boolean $enabled  是否启用SSL
	 * @param boolean $validate 是否验证SSL证书
	 *
	 * @return void
	 */
	public static function setSSL($enabled, $validate = true)
	{
		self::$useSSL = $enabled;
		self::$useSSLValidation = $validate;
	}


	/**
	 * 设置代理信息
	 *
	 * @param string   $host 代理的hostname和端口(localhost:1234)
	 * @param string   $user 代理的username
	 * @param string   $pass 代理的password
	 * @param constant $type CURL代理类型
	 *
	 * @return void
	 */
	public static function setProxy($host, $user = null, $pass = null, $type = CURLPROXY_SOCKS5)
	{
		self::$proxy = ['host' => $host, 'type' => $type, 'user' => $user, 'pass' => $pass];
	}


	/**
	 * 设置是否使用PHP Exception，默认使用trigger_error
	 *
	 * @param boolean $enabled Enable exceptions
	 *
	 * @return void
	 */
	public static function setExceptions($enabled = true)
	{
		self::$useExceptions = $enabled;
	}


	private static function __triggerError($message, $file, $line, $code = 0)
	{
		if (self::$useExceptions) {
			throw new StorageException($message, $file, $line, $code);
		} else {
			trigger_error($message, E_USER_WARNING);
		}
	}


	/**
	 * 获取bucket列表
	 *
	 * @param boolean $detailed 设置为true时返回bucket的详细信息
	 *
	 * @return array | false
	 */
	public static function listBuckets($detailed = false)
	{
		$rest = new StorageRequest('GET', self::$__account, '', '', self::$endpoint);
		$rest->setParameter('format', 'json');
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 200) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::listBuckets(): [%s] %s", $rest->error['code'],
				$rest->error['message']), __FILE__, __LINE__);

			return false;
		}
		$buckets = json_decode($rest->body, true);
		if ($buckets === false) {
			self::__triggerError(sprintf("Storage::listBuckets(): invalid body: %s", $rest->body),
				__FILE__, __LINE__);

			return false;
		}

		if ($detailed) {
			return $buckets;
		}

		$results = [];
		foreach ($buckets as $b) {
			$results[] = (string) $b['name'];
		}

		return $results;
	}


	/**
	 * 获取bucket中的object列表
	 *
	 * @param string $bucket    Bucket名称
	 * @param string $prefix    Object名称的前缀
	 * @param string $marker    Marker (返回marker之后的object列表，不包含marker）
	 * @param string $limit     最大返回的Object数目
	 * @param string $delimiter 分隔符
	 *
	 * @return array | false
	 */
	public static function getBucket($bucket, $prefix = null, $marker = null, $limit = 1000, $delimiter = null)
	{
		$result = [];

		do {
			$rest = new StorageRequest('GET', self::$__account, $bucket, '', self::$endpoint);
			$rest->setParameter('format', 'json');
			if ($prefix !== null && $prefix !== '') {
				$rest->setParameter('prefix', $prefix);
			}
			if ($marker !== null && $marker !== '') {
				$rest->setParameter('marker', $marker);
			}
			if ($delimiter !== null && $delimiter !== '') {
				$rest->setParameter('delimiter', $delimiter);
			} else if (!empty(self::$defDelimiter)) {
				$rest->setParameter('delimiter', self::$defDelimiter);
			}
			if ($limit > 1000) {
				$max_keys = 1000;
			} else {
				$max_keys = $limit;
			}
			$rest->setParameter("limit", $max_keys);
			$limit -= 1000;
			$response = $rest->getResponse();
			if ($response->error === false && $response->code !== 200) {
				$response->error = ['code' => $response->code, 'message' => 'Unexpected HTTP status'];
			}
			if ($response->error !== false) {
				self::__triggerError(sprintf("Storage::getBucket(): [%s] %s", $response->error['code'],
					$response->error['message']), __FILE__, __LINE__);

				return false;
			}

			$objects = json_decode($response->body, true);
			if ($objects === false) {
				self::__triggerError(sprintf("Storage::getBucket(): invalid body: %s", $response->body),
					__FILE__, __LINE__);

				return false;
			}

			if ($objects) {
				$result = array_merge($result, $objects);
				$marker = end($objects);
				$marker = $marker['name'];
			}
		} while ($objects && count($objects) == $max_keys && $limit > 0);

		return $result;
	}


	/**
	 * 创建一个Bucket
	 *
	 * @param string   $bucket      Bucket名称
	 * @param constant $acl         Bucket的ACL
	 * @param array    $metaHeaders x-sws-container-meta-* header数组
	 *
	 * @return boolean
	 */
	public static function putBucket($bucket, $acl = self::ACL_PRIVATE, $metaHeaders = [])
	{
		$rest = new StorageRequest('PUT', self::$__account, $bucket, '', self::$endpoint);
		if ($acl) {
			$rest->setSwsHeader('x-sws-container-read', $acl);
		}
		foreach ($metaHeaders as $k => $v) {
			$rest->setSwsHeader('x-sws-container-meta-' . $k, $v);
		}

		$rest = $rest->getResponse();

		if ($rest->error === false && ($rest->code !== 201 && $rest->code != 202 && $rest->code !== 204)) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::putBucket({$bucket}, {$acl}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return true;
	}

	/**
	 * 获取一个Bucket的属性
	 *
	 * @param string  $bucket     Bucket名称
	 * @param boolean $returnInfo 是否返回Bucket的信息
	 *
	 * @return mixed
	 */
	public static function getBucketInfo($bucket, $returnInfo = true)
	{
		$rest = new StorageRequest('HEAD', self::$__account, $bucket, '', self::$endpoint);
		$rest = $rest->getResponse();
		if ($rest->error === false && ($rest->code !== 204 && $rest->code !== 404)) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::getBucketInfo({$bucket}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return $rest->code !== 404 ? $returnInfo ? $rest->headers : true : false;
	}

	/**
	 * 修改一个Bucket的属性
	 *
	 * @param string   $bucket      Bucket名称
	 * @param constant $acl         Bucket的ACL，null表示不变
	 * @param array    $metaHeaders x-sws-container-meta-* header数组
	 *
	 * @return boolean
	 */
	public static function postBucket($bucket, $acl = null, $metaHeaders = [])
	{
		$rest = new StorageRequest('POST', self::$__account, $bucket, '', self::$endpoint);
		if ($acl) {
			$rest->setSwsHeader('x-sws-container-read', $acl);
		}
		foreach ($metaHeaders as $k => $v) {
			$rest->setSwsHeader('x-sws-container-meta-' . $k, $v);
		}

		$rest = $rest->getResponse();

		if ($rest->error === false && ($rest->code !== 201 && $rest->code !== 204)) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::postBucket({$bucket}, {$acl}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return true;
	}

	/**
	 * 删除一个空的Bucket
	 *
	 * @param string $bucket Bucket名称
	 *
	 * @return boolean
	 */
	public static function deleteBucket($bucket)
	{
		$rest = new StorageRequest('DELETE', self::$__account, $bucket, '', self::$endpoint);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 204) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::deleteBucket({$bucket}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return true;
	}


	/**
	 * 为本地文件路径创建一个可以用于putObject()上传的array
	 *
	 * @param string $file   文件路径
	 * @param mixed  $md5sum Use MD5 hash (supply a string if you want to use your own)
	 *
	 * @return array | false
	 */
	public static function inputFile($file, $md5sum = false)
	{
		if (!file_exists($file) || !is_file($file) || !is_readable($file)) {
			self::__triggerError('Storage::inputFile(): Unable to open input file: ' . $file, __FILE__, __LINE__);

			return false;
		}
		clearstatcache(false, $file);

		return [
			'file' => $file, 'size' => filesize($file), 'md5sum' => $md5sum !== false ?
				(is_string($md5sum) ? $md5sum : base64_encode(md5_file($file, true))) : '',
		];
	}


	/**
	 * 为打开的文件句柄创建一个可以用于putObject()上传的array
	 *
	 * @param string  $resource   Input resource to read from
	 * @param integer $bufferSize Input byte size
	 * @param string  $md5sum     MD5 hash to send (optional)
	 *
	 * @return array | false
	 */
	public static function inputResource(&$resource, $bufferSize = false, $md5sum = '')
	{
		if (!is_resource($resource) || (int) $bufferSize < 0) {
			self::__triggerError('Storage::inputResource(): Invalid resource or buffer size', __FILE__, __LINE__);

			return false;
		}

		// Try to figure out the bytesize
		if ($bufferSize === false) {
			if (fseek($resource, 0, SEEK_END) < 0 || ($bufferSize = ftell($resource)) === false) {
				self::__triggerError('Storage::inputResource(): Unable to obtain resource size', __FILE__, __LINE__);

				return false;
			}
			fseek($resource, 0);
		}

		$input = ['size' => $bufferSize, 'md5sum' => $md5sum];
		$input['fp'] =& $resource;

		return $input;
	}


	/**
	 * 上传一个object
	 *
	 * @param mixed  $input          Input data
	 * @param string $bucket         Bucket name
	 * @param string $uri            Object URI
	 * @param array  $metaHeaders    x-sws-object-meta-* header数组
	 * @param array  $requestHeaders Array of request headers or content type as a string
	 *
	 * @return boolean
	 */
	public static function putObject($input, $bucket, $uri, $metaHeaders = [], $requestHeaders = [])
	{
		if ($input === false) {
			return false;
		}
		$rest = new StorageRequest('PUT', self::$__account, $bucket, $uri, self::$endpoint);

		if (!is_array($input)) {
			$input = [
				'data' => $input, 'size' => strlen($input),
				'md5sum' => base64_encode(md5($input, true)),
			];
		}

		// Data
		if (isset($input['fp'])) {
			$rest->fp =& $input['fp'];
		} elseif (isset($input['file'])) {
			$rest->fp = @fopen($input['file'], 'rb');
		} elseif (isset($input['data'])) {
			$rest->data = $input['data'];
		}

		// Content-Length (required)
		if (isset($input['size']) && $input['size'] >= 0) {
			$rest->size = $input['size'];
		} else {
			if (isset($input['file'])) {
				clearstatcache(false, $input['file']);
				$rest->size = filesize($input['file']);
			} elseif (isset($input['data'])) {
				$rest->size = strlen($input['data']);
			}
		}

		// Custom request headers (Content-Type, Content-Disposition, Content-Encoding)
		if (is_array($requestHeaders)) {
			foreach ($requestHeaders as $h => $v) {
				strpos($h, 'x-') === 0 ? $rest->setSwsHeader($h, $v) : $rest->setHeader($h, $v);
			}
		} elseif (is_string($requestHeaders)) // Support for legacy contentType parameter
		{
			$input['type'] = $requestHeaders;
		}

		// Content-Type
		if (!isset($input['type'])) {
			if (isset($requestHeaders['Content-Type'])) {
				$input['type'] =& $requestHeaders['Content-Type'];
			} elseif (isset($input['file'])) {
				$input['type'] = self::__getMIMEType($input['file']);
			} else {
				$input['type'] = 'application/octet-stream';
			}
		}

		// We need to post with Content-Length and Content-Type, MD5 is optional
		if ($rest->size >= 0 && ($rest->fp !== false || $rest->data !== false)) {
			$rest->setHeader('Content-Type', $input['type']);
			if (isset($input['md5sum'])) {
				$rest->setHeader('Content-MD5', $input['md5sum']);
			}

			foreach ($metaHeaders as $h => $v) {
				$rest->setSwsHeader('x-sws-object-meta-' . $h, $v);
			}
			$rest->getResponse();
		} else {
			$rest->response->error = ['code' => 0, 'message' => 'Missing input parameters'];
		}

		if ($rest->response->error === false && $rest->response->code !== 201) {
			$rest->response->error = ['code' => $rest->response->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->response->error !== false) {
			self::__triggerError(sprintf("Storage::putObject(): [%s] %s",
				$rest->response->error['code'], $rest->response->error['message']), __FILE__, __LINE__);

			return false;
		}

		return true;
	}


	/**
	 * Put an object from a file (legacy function)
	 *
	 * @param string   $file        Input file path
	 * @param string   $bucket      Bucket name
	 * @param string   $uri         Object URI
	 * @param constant $acl         ACL constant
	 * @param array    $metaHeaders Array of x-meta-* headers
	 * @param string   $contentType Content type
	 *
	 * @return boolean
	 */
	public static function putObjectFile($file, $bucket, $uri, $metaHeaders = [], $contentType = null)
	{
		return self::putObject(self::inputFile($file), $bucket, $uri, $metaHeaders, $contentType);
	}


	/**
	 * Put an object from a string (legacy function)
	 *
	 * @param string   $string      Input data
	 * @param string   $bucket      Bucket name
	 * @param string   $uri         Object URI
	 * @param constant $acl         ACL constant
	 * @param array    $metaHeaders Array of x-sws-meta-* headers
	 * @param string   $contentType Content type
	 *
	 * @return boolean
	 */
	public static function putObjectString($string, $bucket, $uri, $metaHeaders = [], $contentType = 'text/plain')
	{
		return self::putObject($string, $bucket, $uri, $metaHeaders, $contentType);
	}

	/**
	 * 修改一个Object的属性
	 *
	 * @param string   $bucket         Bucket名称
	 * @param constant $uri            Object名称
	 * @param array    $metaHeaders    x-sws-container-meta-* header数组
	 * @param array    $requestHeaders 其它header属性
	 *
	 * @return boolean
	 */
	public static function postObject($bucket, $uri, $metaHeaders = [], $requestHeaders = [])
	{
		$rest = new StorageRequest('POST', self::$__account, $bucket, $uri, self::$endpoint);
		foreach ($metaHeaders as $k => $v) {
			$rest->setSwsHeader('x-sws-object-meta-' . $k, $v);
		}
		foreach ($requestHeaders as $k => $v) {
			$rest->setHeader('x-sws-object-meta-' . $k, $v);
		}

		$rest = $rest->getResponse();

		if ($rest->error === false && ($rest->code !== 202)) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::postObject({$bucket}, {$uri}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return true;
	}

	/**
	 * 获取一个Object的内容
	 *
	 * @param string $bucket Bucket名称
	 * @param string $uri    Object名称
	 * @param mixed  $saveTo 文件保存到的文件名或者句柄
	 *
	 * @return mixed 返回服务端返回的response，其中headers为Object的属性信息，body为Object的内容
	 */
	public static function getObject($bucket, $uri, $saveTo = false)
	{
		$rest = new StorageRequest('GET', self::$__account, $bucket, $uri, self::$endpoint);
		if ($saveTo !== false) {
			if (is_resource($saveTo)) {
				$rest->fp =& $saveTo;
			} else if (($rest->fp = @fopen($saveTo, 'wb')) !== false) {
				$rest->file = realpath($saveTo);
			} else {
				$rest->response->error = ['code' => 0, 'message' => 'Unable to open save file for writing: ' . $saveTo];
			}
		}
		if ($rest->response->error === false) {
			$rest->getResponse();
		}

		if ($rest->response->error === false && $rest->response->code !== 200) {
			$rest->response->error = ['code' => $rest->response->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->response->error !== false) {
			self::__triggerError(sprintf("Storage::getObject({$bucket}, {$uri}): [%s] %s",
				$rest->response->error['code'], $rest->response->error['message']), __FILE__, __LINE__);

			return false;
		}

		return $rest->response;
	}


	/**
	 * 获取一个Object的信息
	 *
	 * @param string  $bucket     Bucket名称
	 * @param string  $uri        Object名称
	 * @param boolean $returnInfo 是否返回Object的详细信息
	 *
	 * @return mixed | false
	 */
	public static function getObjectInfo($bucket, $uri, $returnInfo = true)
	{
		$rest = new StorageRequest('HEAD', self::$__account, $bucket, $uri, self::$endpoint);
		$rest = $rest->getResponse();
		if ($rest->error === false && ($rest->code !== 200 && $rest->code !== 404)) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::getObjectInfo({$bucket}, {$uri}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return $rest->code == 200 ? $returnInfo ? $rest->headers : true : false;
	}


	/**
	 * 从一个Bucket复制一个Object到另一个Bucket
	 *
	 * @param string $srcBucket      源Bucket名称
	 * @param string $srcUri         源Object名称
	 * @param string $bucket         目标Bucket名称
	 * @param string $uri            目标Object名称
	 * @param array  $metaHeaders    Optional array of x-sws-meta-* headers
	 * @param array  $requestHeaders Optional array of request headers (content type, disposition, etc.)
	 *
	 * @return mixed | false
	 */
	public static function copyObject($srcBucket, $srcUri, $bucket, $uri, $metaHeaders = [], $requestHeaders = [])
	{
		$rest = new StorageRequest('PUT', self::$__account, $bucket, $uri, self::$endpoint);
		$rest->setHeader('Content-Length', 0);
		foreach ($requestHeaders as $h => $v) {
			strpos($h, 'x-sws-') === 0 ? $rest->setSwsHeader($h, $v) : $rest->setHeader($h, $v);
		}
		foreach ($metaHeaders as $h => $v) {
			$rest->setSwsHeader('x-sws-object-meta-' . $h, $v);
		}
		$rest->setSwsHeader('x-sws-copy-from', sprintf('/%s/%s', $srcBucket, rawurlencode($srcUri)));

		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 201) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::copyObject({$srcBucket}, {$srcUri}, {$bucket}, {$uri}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return isset($rest->body->LastModified, $rest->body->ETag) ? [
			'time' => strtotime((string) $rest->body->LastModified),
			'hash' => substr((string) $rest->body->ETag, 1, -1),
		] : false;
	}


	/**
	 * Set object or bucket Access Control Policy
	 *
	 * @param string $bucket Bucket name
	 * @param string $uri    Object URI
	 * @param array  $acp    Access Control Policy Data (same as the data returned from getAccessControlPolicy)
	 *
	 * @return boolean
	 */
	public static function setAccessControlPolicy($bucket, $uri = '', $acp = [])
	{
	}


	/**
	 * 删除一个Object
	 *
	 * @param string $bucket Bucket名称
	 * @param string $uri    Object名称
	 *
	 * @return boolean
	 */
	public static function deleteObject($bucket, $uri)
	{
		$rest = new StorageRequest('DELETE', self::$__account, $bucket, $uri, self::$endpoint);
		$rest = $rest->getResponse();
		if ($rest->error === false && $rest->code !== 204) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::deleteObject(): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return true;
	}


	/**
	 * 获取一个Object的外网直接访问URL
	 *
	 * @param string $bucket Bucket名称
	 * @param string $uri    Object名称
	 *
	 * @return string
	 */
	public static function getUrl($bucket, $uri)
	{
		return "http://" . self::$__account . '-' . $bucket . '.stor.sinaapp.com/' . rawurlencode($uri);
	}

	/**
	 * 获取一个Object的外网临时访问URL
	 *
	 * @param string $bucket  Bucket名称
	 * @param string $uri     Object名称
	 * @param string $method  Http请求的方法，有GET, PUT, DELETE等
	 * @param int    $seconds 设置这个此URL的过期时间，单位是秒
	 */
	public static function getTempUrl($bucket, $uri, $method, $seconds)
	{
		$expires = (int) (time() + $seconds);
		$path = "/v1/SAE_" . self::$__account . "/" . $bucket . "/" . $uri;
		$hmac_body = $method . "\n" . $expires . "\n" . $path;
		$sig = hash_hmac('sha1', $hmac_body, self::$__secretKey);
		$parameter = http_build_query(["temp_url_sig" => $sig, "temp_url_expires" => $expires]);

		return "http://" . self::$__account . '-' . $bucket . '.stor.sinaapp.com/' . rawurlencode($uri) . "?" . $parameter;
	}

	/**
	 * 获取一个Object的CDN访问URL
	 *
	 * @param string $bucket Bucket名称
	 * @param string $uri    Object名称
	 *
	 * @return string
	 */
	public static function getCdnUrl($bucket, $uri)
	{
		return "http://" . self::$__account . '.sae.sinacn.com/.app-stor/' . $bucket . '/' . rawurlencode($uri);
	}

	/**
	 * 修改账户的属性（for internal use onley）
	 *
	 * @param array $metaHeaders x-sws-account-meta-* header数组
	 *
	 * @return boolean
	 */
	public static function postAccount($metaHeaders = [])
	{
		$rest = new StorageRequest('POST', self::$__account, '', '', self::$endpoint);
		foreach ($metaHeaders as $k => $v) {
			$rest->setSwsHeader('x-sws-account-meta-' . $k, $v);
		}

		$rest = $rest->getResponse();

		if ($rest->error === false && ($rest->code !== 201 && $rest->code !== 204)) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::postAccount({$bucket}, {$acl}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return true;
	}

	/**
	 * 获取账户的属性（for internal use only）
	 *
	 * @param string $bucket Bucket名称
	 *
	 * @return mixed
	 */
	public static function getAccountInfo()
	{
		$rest = new StorageRequest('HEAD', self::$__account, '', '', self::$endpoint);
		$rest = $rest->getResponse();
		if ($rest->error === false && ($rest->code !== 204 && $rest->code !== 404)) {
			$rest->error = ['code' => $rest->code, 'message' => 'Unexpected HTTP status'];
		}
		if ($rest->error !== false) {
			self::__triggerError(sprintf("Storage::getAccountInfo({$bucket}): [%s] %s",
				$rest->error['code'], $rest->error['message']), __FILE__, __LINE__);

			return false;
		}

		return $rest->code !== 404 ? $rest->headers : false;
	}


	private static function __getMIMEType(&$file)
	{
		static $exts = [
			'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
			'png' => 'image/png', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf',
			'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'svg' => 'image/svg+xml',
			'svgz' => 'image/svg+xml', 'swf' => 'application/x-shockwave-flash',
			'zip' => 'application/zip', 'gz' => 'application/x-gzip',
			'tar' => 'application/x-tar', 'bz' => 'application/x-bzip',
			'bz2' => 'application/x-bzip2', 'rar' => 'application/x-rar-compressed',
			'exe' => 'application/x-msdownload', 'msi' => 'application/x-msdownload',
			'cab' => 'application/vnd.ms-cab-compressed', 'txt' => 'text/plain',
			'asc' => 'text/plain', 'htm' => 'text/html', 'html' => 'text/html',
			'css' => 'text/css', 'js' => 'text/javascript',
			'xml' => 'text/xml', 'xsl' => 'application/xsl+xml',
			'ogg' => 'application/ogg', 'mp3' => 'audio/mpeg', 'wav' => 'audio/x-wav',
			'avi' => 'video/x-msvideo', 'mpg' => 'video/mpeg', 'mpeg' => 'video/mpeg',
			'mov' => 'video/quicktime', 'flv' => 'video/x-flv', 'php' => 'text/x-php',
		];

		$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
		if (isset($exts[$ext])) {
			return $exts[$ext];
		}

		// Use fileinfo if available
		if (extension_loaded('fileinfo') && isset($_ENV['MAGIC']) &&
			($finfo = finfo_open(FILEINFO_MIME, $_ENV['MAGIC'])) !== false
		) {
			if (($type = finfo_file($finfo, $file)) !== false) {
				// Remove the charset and grab the last content-type
				$type = explode(' ', str_replace('; charset=', ';charset=', $type));
				$type = array_pop($type);
				$type = explode(';', $type);
				$type = trim(array_shift($type));
			}
			finfo_close($finfo);
			if ($type !== false && strlen($type) > 0) {
				return $type;
			}
		}

		return 'application/octet-stream';
	}


	public static function __getTime()
	{
		return time() + self::$__timeOffset;
	}


	public static function __getSignature($string)
	{
		//var_dump("sign:", $string);
		return 'SWS ' . self::$__accessKey . ':' . self::__getHash($string);
	}


	private static function __getHash($string)
	{
		return base64_encode(extension_loaded('hash') ?
			hash_hmac('sha1', $string, self::$__secretKey, true) : pack('H*', sha1(
				(str_pad(self::$__secretKey, 64, chr(0x00)) ^ (str_repeat(chr(0x5c), 64))) .
				pack('H*', sha1((str_pad(self::$__secretKey, 64, chr(0x00)) ^
						(str_repeat(chr(0x36), 64))) . $string)))));
	}

}

/**
 * @ignore
 */
final class StorageRequest
{
	private $endpoint;
	private $verb;
	private $uri;
	private $parameters = [];
	private $swsHeaders = [];
	private $headers = [
		'Host' => '', 'Date' => '', 'Content-MD5' => '', 'Content-Type' => '',
	];

	public $fp = false;
	public $size = 0;
	public $data = false;

	public $response;


	function __construct($verb, $account, $bucket = '', $uri = '', $endpoint = DEFAULT_STORAGE_ENDPOINT)
	{
		$this->endpoint = $endpoint;
		$this->verb = $verb;

		$this->uri = "/v1/SAE_" . rawurlencode($account);
		$this->resource = "/$account";
		if ($bucket !== '') {
			$this->uri = $this->uri . '/' . rawurlencode($bucket);
			$this->resource = $this->resource . '/' . $bucket;
		}
		if ($uri !== '') {
			$this->uri .= '/' . str_replace('%2F', '/', rawurlencode($uri));
			$this->resource = $this->resource . '/' . str_replace(' ', '%20', $uri);
		}

		$this->headers['Host'] = $this->endpoint;
		$this->headers['Date'] = gmdate('D, d M Y H:i:s T');
		$this->response = new \STDClass;
		$this->response->error = false;
		$this->response->body = null;
		$this->response->headers = [];
	}


	public function setParameter($key, $value)
	{
		$this->parameters[$key] = $value;
	}


	public function setHeader($key, $value)
	{
		$this->headers[$key] = $value;
	}


	public function setSwsHeader($key, $value)
	{
		$this->swsHeaders[$key] = $value;
	}


	public function getResponse()
	{
		$query = '';
		if (sizeof($this->parameters) > 0) {
			$query = substr($this->uri, -1) !== '?' ? '?' : '&';
			foreach ($this->parameters as $var => $value) {
				if ($value == null || $value == '') {
					$query .= $var . '&';
				} else {
					$query .= $var . '=' . rawurlencode($value) . '&';
				}
			}
			$query = substr($query, 0, -1);
			$this->uri .= $query;
			$this->resource .= $query;
		}
		$url = (Storage::$useSSL ? 'https://' : 'http://') . ($this->headers['Host'] !== '' ? $this->headers['Host'] : $this->endpoint) . $this->uri;

		//var_dump('uri: ' . $this->uri, 'url: ' . $url, 'resource: ' . $this->resource);

		// Basic setup
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_USERAGENT, 'Storage/php');

		if (Storage::$useSSL) {
			// Set protocol version
			curl_setopt($curl, CURLOPT_SSLVERSION, Storage::$useSSLVersion);

			// SSL Validation can now be optional for those with broken OpenSSL installations
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, Storage::$useSSLValidation ? 2 : 0);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, Storage::$useSSLValidation ? 1 : 0);
		}

		curl_setopt($curl, CURLOPT_URL, $url);

		if (Storage::$proxy != null && isset(Storage::$proxy['host'])) {
			curl_setopt($curl, CURLOPT_PROXY, Storage::$proxy['host']);
			curl_setopt($curl, CURLOPT_PROXYTYPE, Storage::$proxy['type']);
			if (isset(Storage::$proxy['user'], Storage::$proxy['pass']) && Storage::$proxy['user'] != null && Storage::$proxy['pass'] != null) {
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, sprintf('%s:%s', Storage::$proxy['user'], Storage::$proxy['pass']));
			}
		}

		// Headers
		$headers = [];
		$sae = [];
		foreach ($this->swsHeaders as $header => $value) {
			if (strlen($value) > 0) {
				$headers[] = $header . ': ' . $value;
			}
		}
		foreach ($this->headers as $header => $value) {
			if (strlen($value) > 0) {
				$headers[] = $header . ': ' . $value;
			}
		}

		foreach ($this->swsHeaders as $header => $value) {
			if (strlen($value) > 0) {
				$sae[] = strtolower($header) . ':' . $value;
			}
		}

		if (sizeof($sae) > 0) {
			usort($sae, [&$this, '__sortMetaHeadersCmp']);
			$sae = "\n" . implode("\n", $sae);
		} else {
			$sae = '';
		}

		if (Storage::hasAuth()) {
			$headers[] = 'Authorization: ' . Storage::__getSignature(
					$this->verb . "\n" .
					$this->headers['Date'] . $sae . "\n" .
					$this->resource
				);
		}

		//var_dump("headers:", $headers);

		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($curl, CURLOPT_HEADER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, [&$this, '__responseWriteCallback']);
		curl_setopt($curl, CURLOPT_HEADERFUNCTION, [&$this, '__responseHeaderCallback']);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

		// Request types
		switch ($this->verb) {
			case 'GET':
				break;
			case 'PUT':
			case 'POST':
				if ($this->fp !== false) {
					curl_setopt($curl, CURLOPT_PUT, true);
					curl_setopt($curl, CURLOPT_INFILE, $this->fp);
					if ($this->size >= 0) {
						curl_setopt($curl, CURLOPT_INFILESIZE, $this->size);
					}
				} elseif ($this->data !== false) {
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
					curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
				} else {
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->verb);
				}
				break;
			case 'HEAD':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'HEAD');
				curl_setopt($curl, CURLOPT_NOBODY, true);
				break;
			case 'DELETE':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
				break;
			default:
				break;
		}

		// Execute, grab errors
		if (curl_exec($curl)) {
			$this->response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		} else {
			$this->response->error = [
				'code' => curl_errno($curl),
				'message' => curl_error($curl),
			];
		}

		@curl_close($curl);

		// Clean up file resources
		if ($this->fp !== false && is_resource($this->fp)) {
			fclose($this->fp);
		}

		//var_dump("response:", $this->response);
		return $this->response;
	}


	private function __sortMetaHeadersCmp($a, $b)
	{
		$lenA = strpos($a, ':');
		$lenB = strpos($b, ':');
		$minLen = min($lenA, $lenB);
		$ncmp = strncmp($a, $b, $minLen);
		if ($lenA == $lenB) {
			return $ncmp;
		}
		if (0 == $ncmp) {
			return $lenA < $lenB ? -1 : 1;
		}

		return $ncmp;
	}


	private function __responseWriteCallback(&$curl, &$data)
	{
		if (in_array($this->response->code, [200, 206]) && $this->fp !== false) {
			return fwrite($this->fp, $data);
		} else {
			$this->response->body .= $data;
		}

		return strlen($data);
	}


	private function __responseHeaderCallback($curl, $data)
	{
		if (($strlen = strlen($data)) <= 2) {
			return $strlen;
		}
		if (substr($data, 0, 4) == 'HTTP') {
			$this->response->code = (int) substr($data, 9, 3);
		} else {
			$data = trim($data);
			if (strpos($data, ': ') === false) {
				return $strlen;
			}
			list($header, $value) = explode(': ', $data, 2);
			if ($header == 'Last-Modified') {
				$this->response->headers['time'] = strtotime($value);
			} elseif ($header == 'Date') {
				$this->response->headers['date'] = strtotime($value);
			} elseif ($header == 'Content-Length') {
				$this->response->headers['size'] = (int) $value;
			} elseif ($header == 'Content-Type') {
				$this->response->headers['type'] = $value;
			} elseif ($header == 'ETag') {
				$this->response->headers['hash'] = $value{0} == '"' ? substr($value, 1, -1) : $value;
			} elseif (preg_match('/^x-sws-(?:account|container|object)-read$/i', $header)) {
				$this->response->headers['acl'] = $value;
			} elseif (preg_match('/^x-sws-(?:account|container|object)-meta-(.*)$/i', $header)) {
				$this->response->headers[strtolower($header)] = $value;
			} elseif (preg_match('/^x-sws-(?:account|container|object)-(.*)$/i', $header, $m)) {
				$this->response->headers[strtolower($m[1])] = $value;
			}
		}

		return $strlen;
	}

}

/**
 * Storage异常类
 */
class StorageException extends \Exception
{
	/**
	 * 构造函数
	 *
	 * @param string $message 异常信息
	 * @param string $file    抛出异常的文件
	 * @param string $line    抛出异常的代码行
	 * @param int    $code    异常码
	 */
	function __construct($message, $file, $line, $code = 0)
	{
		parent::__construct($message, $code);
		$this->file = $file;
		$this->line = $line;
	}
}

/**
 * A PHP wrapper for Storage
 *
 * @ignore
 */
final class StorageWrapper extends Storage
{
	private $position = 0, $mode = '', $buffer;

	public function url_stat($path, $flags)
	{
		self::__getURL($path);

		return (($info = self::getObjectInfo($this->url['host'], $this->url['path'])) !== false) ?
			['size' => $info['size'], 'mtime' => $info['time'], 'ctime' => $info['time']] : false;
	}

	public function unlink($path)
	{
		self::__getURL($path);

		return self::deleteObject($this->url['host'], $this->url['path']);
	}

	public function mkdir($path, $mode, $options)
	{
		self::__getURL($path);

		return self::putBucket($this->url['host'], self::__translateMode($mode));
	}

	public function rmdir($path)
	{
		self::__getURL($path);

		return self::deleteBucket($this->url['host']);
	}

	public function dir_opendir($path, $options)
	{
		self::__getURL($path);
		if (($contents = self::getBucket($this->url['host'], $this->url['path'])) !== false) {
			$pathlen = strlen($this->url['path']);
			if (substr($this->url['path'], -1) == '/') {
				$pathlen++;
			}
			$this->buffer = [];
			foreach ($contents as $file) {
				if ($pathlen > 0) {
					$file['name'] = substr($file['name'], $pathlen);
				}
				$this->buffer[] = $file;
			}

			return true;
		}

		return false;
	}

	public function dir_readdir()
	{
		return (isset($this->buffer[$this->position])) ? $this->buffer[$this->position++]['name'] : false;
	}

	public function dir_rewinddir()
	{
		$this->position = 0;
	}

	public function dir_closedir()
	{
		$this->position = 0;
		unset($this->buffer);
	}

	public function stream_close()
	{
		if ($this->mode == 'w') {
			self::putObject($this->buffer, $this->url['host'], $this->url['path']);
		}
		$this->position = 0;
		unset($this->buffer);
	}

	public function stream_stat()
	{
		if (is_object($this->buffer) && isset($this->buffer->headers)) {
			return [
				'size' => $this->buffer->headers['size'],
				'mtime' => $this->buffer->headers['time'],
				'ctime' => $this->buffer->headers['time'],
			];
		} elseif (($info = self::getObjectInfo($this->url['host'], $this->url['path'])) !== false) {
			return ['size' => $info['size'], 'mtime' => $info['time'], 'ctime' => $info['time']];
		}

		return false;
	}

	public function stream_flush()
	{
		$this->position = 0;

		return true;
	}

	public function stream_open($path, $mode, $options, &$opened_path)
	{
		if (!in_array($mode, ['r', 'rb', 'w', 'wb'])) {
			return false;
		} // Mode not supported
		$this->mode = substr($mode, 0, 1);
		self::__getURL($path);
		$this->position = 0;
		if ($this->mode == 'r') {
			if (($this->buffer = self::getObject($this->url['host'], $this->url['path'])) !== false) {
				if (is_object($this->buffer->body)) {
					$this->buffer->body = (string) $this->buffer->body;
				}
			} else {
				return false;
			}
		}

		return true;
	}

	public function stream_read($count)
	{
		if ($this->mode !== 'r' && $this->buffer !== false) {
			return false;
		}
		$data = substr(is_object($this->buffer) ? $this->buffer->body : $this->buffer, $this->position, $count);
		$this->position += strlen($data);

		return $data;
	}

	public function stream_write($data)
	{
		if ($this->mode !== 'w') {
			return 0;
		}
		$left = substr($this->buffer, 0, $this->position);
		$right = substr($this->buffer, $this->position + strlen($data));
		$this->buffer = $left . $data . $right;
		$this->position += strlen($data);

		return strlen($data);
	}

	public function stream_tell()
	{
		return $this->position;
	}

	public function stream_eof()
	{
		return $this->position >= strlen(is_object($this->buffer) ? $this->buffer->body : $this->buffer);
	}

	public function stream_seek($offset, $whence)
	{
		switch ($whence) {
			case SEEK_SET:
				if ($offset < strlen($this->buffer->body) && $offset >= 0) {
					$this->position = $offset;

					return true;
				} else {
					return false;
				}
				break;
			case SEEK_CUR:
				if ($offset >= 0) {
					$this->position += $offset;

					return true;
				} else {
					return false;
				}
				break;
			case SEEK_END:
				$bytes = strlen($this->buffer->body);
				if ($bytes + $offset >= 0) {
					$this->position = $bytes + $offset;

					return true;
				} else {
					return false;
				}
				break;
			default:
				return false;
		}
	}

	private function __getURL($path)
	{
		$this->url = parse_url($path);
		if (!isset($this->url['scheme']) || $this->url['scheme'] !== 'storage') {
			return $this->url;
		}
		if (isset($this->url['user'], $this->url['pass'])) {
			self::setAuth($this->url['user'], $this->url['pass']);
		}
		$this->url['path'] = isset($this->url['path']) ? substr($this->url['path'], 1) : '';
	}

	private function __translateMode($mode)
	{
		$acl = self::ACL_PRIVATE;
		if (($mode & 0x0020) || ($mode & 0x0004)) {
			$acl = self::ACL_PUBLIC_READ;
		}
		// You probably don't want to enable public write access
		if (($mode & 0x0010) || ($mode & 0x0008) || ($mode & 0x0002) || ($mode & 0x0001)) {
			$acl = self::ACL_PUBLIC_READ;
		} //$acl = self::ACL_PUBLIC_READ_WRITE;
		return $acl;
	}
}

stream_wrapper_register('storage', 'Litp\Flysystem\StorageWrapper');

