<?php

namespace Hail\Filesystem\Adapter;

use InvalidArgumentException;
use Hail\Filesystem\Adapter\Polyfill\StreamedCopyTrait;
use Hail\Filesystem\AdapterInterface;
use Hail\Filesystem\Util;
use LogicException;
use RuntimeException;

class Sftp extends AbstractFtpAdapter
{
	use StreamedCopyTrait;

	/**
	 * @var int
	 */
	protected $port = 22;

	/**
	 * @var array
	 */
	protected $configurable = ['host', 'port', 'username', 'password', 'timeout', 'root', 'privateKey', 'publicKey', 'permPrivate', 'permPublic', 'directoryPerm', 'NetSftpConnection'];

	/**
	 * @var int
	 */
	protected $directoryPerm = 0744;

	protected $sftp;

	/**
	 * Prefix a path.
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	protected function prefix($path)
	{
		return $this->root . ltrim($path, $this->separator);
	}

	/**
	 * Set the private key (string or path to local file).
	 *
	 * @param string $key
	 *
	 * @return $this
	 */
	public function setPrivateKey($key)
	{
		$this->safeStorage->set('privateKey', $key);

		return $this;
	}

	public function getPrivateKey()
	{
		return $this->safeStorage->get('privateKey');
	}

	public function setPublicKey($key)
	{
		$this->safeStorage->set('publicKey', $key);

		return $this;
	}

	public function getPublicKey()
	{
		return $this->safeStorage->get('publicKey');
	}

	/**
	 * Set permissions for new directory
	 *
	 * @param int $directoryPerm
	 *
	 * @return $this
	 */
	public function setDirectoryPerm($directoryPerm)
	{
		$this->directoryPerm = $directoryPerm;

		return $this;
	}

	/**
	 * Get permissions for new directory
	 *
	 * @return int
	 */
	public function getDirectoryPerm()
	{
		return $this->directoryPerm;
	}

	/**
	 * Inject the SFTP instance.
	 *
	 * @param resource $connection
	 * @param resource $sftp
	 *
	 * @return $this
	 */
	public function setNetSftpConnection($connection, $sftp)
	{
		$this->connection = $connection;
		$this->sftp = $sftp;

		return $this;
	}

	/**
	 * Connect.
	 *
	 * @throws LogicException
	 * @throws RuntimeException
	 */
	public function connect()
	{
		$this->connection = $this->connection ?: @ssh2_connect($this->host, $this->port);
		if (!$this->connection) {
			throw new LogicException("connection to {$this->host} failed");
		}

		if (!$this->login()) {
			throw new LogicException('login failed');
		}

		if (!$this->sftp = @ssh2_sftp($this->connection)) {
			throw new LogicException("unable to establish sftp connection with {$this->host}");
		}

		$this->setConnectionRoot();
	}

	/**
	 * Login.
	 */
	protected function login()
	{
		$user = $this->getUsername();
		$password = $this->getPassword();
		$privateKey = $this->getPrivateKey();
		$publicKey = $this->getPublicKey();

		if (file_exists($privateKey) && file_exists($publicKey)) {
			return @ssh2_auth_pubkey_file($this->connection, $user, $publicKey, $privateKey, $password);
		}

		if ($password) {
			return @ssh2_auth_password($this->connection, $user, $password);
		}

		return @ssh2_auth_none($this->connection, $user);
	}

	/**
	 * Set the connection root.
	 *
	 * @throws RuntimeException
	 */
	protected function setConnectionRoot()
	{
		$root = $this->getRoot();

		if (!$root) {
			return;
		}

		if (!$this->root = @ssh2_sftp_realpath($this->sftp, $root)) {
			throw new RuntimeException('Root is invalid or does not exist: ' . $root);
		}

		$this->root = $root . $this->separator;
	}

	/**
	 * List the contents of a directory.
	 *
	 * @param string $directory
	 * @param bool   $recursive
	 *
	 * @return array
	 */
	protected function listDirectoryContents($directory, $recursive = true)
	{
		$result = [
			[]
		];
		$connection = $this->getConnection();
		$location = $this->prefix($directory);

		if (!$stream = @ssh2_exec($connection, "ls {$location}")) {
			return [];
		}

		stream_set_blocking($stream, true);
		$cmd = fread($stream, 4096);
		$listing = explode("\n", $cmd);

		foreach ($listing as $filename) {
			if (in_array($filename, ['.', '..'], true)) {
				continue;
			}

			$path = empty($directory) ? $filename : ($directory . '/' . $filename);
			$stat = $this->getMetadata($path);
			$result[0][] = $stat;

			if ($recursive && $stat['type'] === 'dir') {
				$result[] = $this->listDirectoryContents($path);
			}
		}

		return !isset($result[1]) ? $result[0] : call_user_func_array('array_merge', $result);
	}

	/**
	 * Disconnect.
	 */
	public function disconnect()
	{
		$this->connection = null;
	}

	/**
	 * @inheritdoc
	 */
	public function write($path, $contents, array $config)
	{
		$this->ensureDirectory(Util::dirname($path));

		$stream = fopen('ssh2.sftp://' . $this->sftp . $path, 'w+b');
		if (!$stream) {
			return false;
		}


		if (fwrite($stream, $contents) === false) {
			fclose($stream);

			return false;
		}

		fclose($stream);

		if ($visibility = $config['visibility'] ?? null) {
			$this->setVisibility($path, $visibility);
		}

		return compact('visibility', 'contents', 'path');
	}

	/**
	 * @inheritdoc
	 */
	public function writeStream($path, $resource, array $config)
	{
		$this->ensureDirectory(Util::dirname($path));

		$stream = fopen('ssh2.sftp://' . $this->sftp . $path, 'w+b');
		if (!$stream) {
			return false;
		}

		stream_copy_to_stream($resource, $stream);
		if (!fclose($stream)) {
			return false;
		}

		if ($visibility = $config['visibility'] ?? null) {
			$this->setVisibility($path, $visibility);
		}

		return compact('visibility', 'path');
	}

	/**
	 * @inheritdoc
	 */
	public function read($path)
	{
		$stream = fopen('ssh2.sftp://' . $this->sftp . $path, 'rb');
		if (!$stream) {
			return false;
		}

		if ($contents = stream_get_contents($stream) === false) {
			return false;
		}

		return compact('contents');
	}

	/**
	 * @inheritdoc
	 */
	public function readStream($path)
	{
		$stream = fopen('ssh2.sftp://' . $this->sftp . $path, 'rb');
		if (!$stream) {
			return false;
		}

		return compact('stream');
	}

	/**
	 * @inheritdoc
	 */
	public function update($path, $contents, array $config)
	{
		return $this->write($path, $contents, $config);
	}

	/**
	 * @inheritdoc
	 */
	public function updateStream($path, $contents, array $config)
	{
		return $this->writeStream($path, $contents, $config);
	}

	/**
	 * @inheritdoc
	 */
	public function delete($path)
	{
		return ssh2_sftp_unlink($this->sftp, $path);
	}

	/**
	 * @inheritdoc
	 */
	public function rename($path, $newpath)
	{
		return ssh2_sftp_rename($this->sftp, $path, $newpath);
	}

	/**
	 * @inheritdoc
	 */
	public function deleteDir($dirname)
	{
		return ssh2_sftp_rmdir($this->sftp, $dirname);
	}

	/**
	 * @inheritdoc
	 */
	public function has($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritdoc
	 */
	public function getMetadata($path)
	{
		$info = ssh2_sftp_stat($this->sftp, $path);

		if ($info === false) {
			return false;
		}

		$type = ($info['mode'] & 040000) ? 'dir' : 'file';

		$permissions = $this->normalizePermissions($info['mode']);

		$timestamp = $info['mtime'];

		$visibility = ($permissions & 0044) ? AdapterInterface::VISIBILITY_PUBLIC : AdapterInterface::VISIBILITY_PRIVATE;
		$size = (int) $info['size'];

		return compact('path', 'timestamp', 'type', 'visibility', 'size');
	}

	/**
	 * @inheritdoc
	 */
	public function getTimestamp($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritdoc
	 */
	public function getMimetype($path)
	{
		if (!$data = $this->has($path)) {
			return false;
		}

		$data['mimetype'] = Util::guessMimeType($path, $data['contents']);

		return $data;
	}

	/**
	 * @inheritdoc
	 */
	public function createDir($dirname, array $config)
	{
		if (!ssh2_sftp_mkdir($this->sftp, $dirname, $this->getDirectoryPerm(), true)) {
			return false;
		}

		return ['path' => $dirname];
	}

	/**
	 * @inheritdoc
	 */
	public function getVisibility($path)
	{
		return $this->getMetadata($path);
	}

	/**
	 * @inheritdoc
	 */
	public function setVisibility($path, $visibility)
	{
		$visibility = ucfirst($visibility);

		if (!isset($this->{'perm' . $visibility})) {
			throw new InvalidArgumentException('Unknown visibility: ' . $visibility);
		}

		return @ssh2_sftp_chmod($this->sftp, $path, $this->{'perm' . $visibility});
	}

	/**
	 * @inheritdoc
	 */
	public function isConnected()
	{
		if ($this->connection instanceof SFTP && $this->connection->isConnected()) {
			return true;
		}

		return false;
	}
}

