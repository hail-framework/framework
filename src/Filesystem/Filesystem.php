<?php

namespace Hail\Filesystem;

use Hail\Filesystem\Exception\{
    FileExistsException, FileNotFoundException, RootViolationException
};
use Hail\Filesystem\Util\ContentListingFormatter;

/**
 * Class Filesystem
 *
 * @package Hail\Filesystem
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Filesystem implements FilesystemInterface
{
    use PluginTrait;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var array
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        if (!isset($config['adapter']) || !\class_exists($class = '\\' . __NAMESPACE__ . '\\Adapter\\' . \ucfirst($config['adapter']))) {
            throw new \InvalidArgumentException('File system adapter not defined');
        }

        $this->adapter = new $class($config);
        $this->config = $config['config'] ?? [];
    }

    /**
     * Get the Adapter.
     *
     * @return AdapterInterface adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * Get the Config.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getConfig(string $name = null, $default = null)
    {
        if ($name === null) {
            return $this->config;
        }

        return $this->config[$name] ?? $default;
    }

    /**
     * Check whether a file exists.
     *
     * @param string $path
     *
     * @return bool
     */
    public function has(string $path)
    {
        $path = Util::normalizePath($path);

        return $path === '' ? false : (bool) $this->adapter->has($path);
    }

    /**
     * Write a new file.
     *
     * @param string $path     The path of the new file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @return bool True on success, false on failure.
     * @throws FileExistsException
     */
    public function write($path, $contents, array $config = [])
    {
        $this->assertAbsent($path);
        $path = Util::normalizePath($path);
        $config += $this->config;

        return (bool) $this->adapter->write($path, $contents, $config);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path     The path of the new file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @return bool True on success, false on failure.
     * @throws FileExistsException
     */
    public function writeStream($path, $resource, array $config = [])
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException(__METHOD__ . ' expects argument #2 to be a valid resource.');
        }

        $this->assertAbsent($path);
        $path = Util::normalizePath($path);
        $config += $this->config;

        Util::rewindStream($resource);

        return (bool) $this->adapter->writeStream($path, $resource, $config);
    }

    /**
     * Create a file or update if exists.
     *
     * @param string $path     The path to the file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @return bool True on success, false on failure.
     */
    public function put($path, $contents, array $config = [])
    {
        $path = Util::normalizePath($path);
        $config += $this->config;

        if (!$this->adapter->canOverwrite() && $this->has($path)) {
            return (bool) $this->adapter->update($path, $contents, $config);
        }

        return (bool) $this->adapter->write($path, $contents, $config);
    }

    /**
     * Create a file or update if exists.
     *
     * @param string   $path     The path to the file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @return bool True on success, false on failure.
     */
    public function putStream($path, $resource, array $config = [])
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException(__METHOD__ . ' expects argument #2 to be a valid resource.');
        }

        $path = Util::normalizePath($path);
        $config += $this->config;
        Util::rewindStream($resource);

        if (!$this->adapter->canOverwrite() && $this->has($path)) {
            return (bool) $this->adapter->updateStream($path, $resource, $config);
        }

        return (bool) $this->adapter->writeStream($path, $resource, $config);
    }

    /**
     * Read and delete a file.
     *
     * @param string $path The path to the file.
     *
     * @return string|false The file contents, or false on failure.
     * @throws FileNotFoundException
     */
    public function readAndDelete($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        $contents = $this->read($path);

        if ($contents === false) {
            return false;
        }

        $this->delete($path);

        return $contents;
    }

    /**
     * Update an existing file.
     *
     * @param string $path     The path of the existing file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @return bool True on success, false on failure.
     * @throws FileNotFoundException
     */
    public function update($path, $contents, array $config = [])
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        $config += $this->config;

        return (bool) $this->adapter->update($path, $contents, $config);
    }

    /**
     * Update an existing file using a stream.
     *
     * @param string   $path     The path of the existing file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @throws FileNotFoundException
     *
     * @return bool True on success, false on failure.
     */
    public function updateStream($path, $resource, array $config = [])
    {
        if (!\is_resource($resource)) {
            throw new \InvalidArgumentException(__METHOD__ . ' expects argument #2 to be a valid resource.');
        }

        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        $config += $this->config;
        Util::rewindStream($resource);

        return (bool) $this->adapter->updateStream($path, $resource, $config);
    }

    /**
     * Read a file.
     *
     * @param string $path The path to the file.
     *
     * @return string|false The file contents or false on failure.
     * @throws FileNotFoundException
     */
    public function read($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        if (!($object = $this->adapter->read($path))) {
            return false;
        }

        return $object['contents'];
    }

    /**
     * Retrieves a read-stream for a path.
     *
     * @param string $path The path to the file.
     *
     * @return resource|false The path resource or false on failure.
     * @throws FileNotFoundException
     */
    public function readStream($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        if (!$object = $this->adapter->readStream($path)) {
            return false;
        }

        return $object['stream'];
    }

    /**
     * Rename a file.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @return bool True on success, false on failure.
     * @throws FileExistsException   Thrown if $newpath exists.
     * @throws FileNotFoundException Thrown if $path does not exist.
     */
    public function rename($path, $newpath)
    {
        $this->assertPresent($path);
        $this->assertAbsent($newpath);
        $path = Util::normalizePath($path);
        $newpath = Util::normalizePath($newpath);

        return (bool) $this->adapter->rename($path, $newpath);
    }

    /**
     * Copy a file.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @return bool True on success, false on failure.
     * @throws FileExistsException   Thrown if $newpath exists.
     * @throws FileNotFoundException Thrown if $path does not exist.
     */
    public function copy($path, $newpath)
    {
        $this->assertPresent($path);
        $this->assertAbsent($newpath);
        $path = Util::normalizePath($path);
        $newpath = Util::normalizePath($newpath);

        return $this->adapter->copy($path, $newpath);
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @return bool True on success, false on failure.
     * @throws FileNotFoundException
     */
    public function delete($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);

        return $this->adapter->delete($path);
    }

    /**
     * Delete a directory.
     *
     * @param string $dirname
     *
     * @throws RootViolationException Thrown if $dirname is empty.
     *
     * @return bool True on success, false on failure.
     */
    public function deleteDir($dirname)
    {
        $dirname = Util::normalizePath($dirname);

        if ($dirname === '') {
            throw new RootViolationException('Root directories can not be deleted.');
        }

        return (bool) $this->adapter->deleteDir($dirname);
    }

    /**
     * Create a directory.
     *
     * @param string $dirname The name of the new directory.
     * @param array  $config  An optional configuration array.
     *
     * @return bool True on success, false on failure.
     */
    public function createDir($dirname, array $config = [])
    {
        $dirname = Util::normalizePath($dirname);
        $config += $this->config;

        return (bool) $this->adapter->createDir($dirname, $config);
    }

    /**
     * List contents of a directory.
     *
     * @param string $directory The directory to list.
     * @param bool   $recursive Whether to list recursively.
     *
     * @return array A list of file metadata.
     */
    public function listContents($directory = '', $recursive = false)
    {
        $directory = Util::normalizePath($directory);
        $contents = $this->adapter->listContents($directory, $recursive);

        return ContentListingFormatter::formatListing($directory, $recursive, $contents);
    }

    /**
     * Get a file's mime-type.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return string|false The file mime-type or false on failure.
     */
    public function getMimetype($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        if ((!$object = $this->adapter->getMimetype($path)) || !\array_key_exists('mimetype', $object)) {
            return false;
        }

        return $object['mimetype'];
    }

    /**
     * Get a file's timestamp.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return string|false The timestamp or false on failure.
     */
    public function getTimestamp($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        if ((!$object = $this->adapter->getTimestamp($path)) || !\array_key_exists('timestamp', $object)) {
            return false;
        }

        return $object['timestamp'];
    }

    /**
     * Get a file's visibility.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return string|false The visibility (public|private) or false on failure.
     */
    public function getVisibility($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);
        if ((!$object = $this->adapter->getVisibility($path)) || !\array_key_exists('visibility', $object)) {
            return false;
        }

        return $object['visibility'];
    }

    /**
     * Get a file's size.
     *
     * @param string $path The path to the file.
     *
     * @return int|false The file size or false on failure.
     */
    public function getSize($path)
    {
        $path = Util::normalizePath($path);

        if ((!$object = $this->adapter->getSize($path)) || !\array_key_exists('size', $object)) {
            return false;
        }

        return (int) $object['size'];
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path       The path to the file.
     * @param string $visibility One of 'public' or 'private'.
     *
     * @return bool True on success, false on failure.
     */
    public function setVisibility($path, $visibility)
    {
        $path = Util::normalizePath($path);

        return (bool) $this->adapter->setVisibility($path, $visibility);
    }

    /**
     * Get a file's metadata.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return array|false The file metadata or false on failure.
     */
    public function getMetadata($path)
    {
        $this->assertPresent($path);
        $path = Util::normalizePath($path);

        return $this->adapter->getMetadata($path);
    }

    /**
     * Get a file/directory handler.
     *
     * @param string  $path    The path to the file.
     * @param Handler $handler An optional existing handler to populate.
     *
     * @return Handler Either a file or directory handler.
     * @throws FileNotFoundException
     */
    public function get($path, Handler $handler = null)
    {
        $path = Util::normalizePath($path);

        if (!$handler) {
            $metadata = $this->getMetadata($path);
            $handler = $metadata['type'] === 'file' ? new File($this, $path) : new Directory($this, $path);
        } else {
            $handler->setPath($path);
            $handler->setFilesystem($this);
        }

        return $handler;
    }

    /**
     * Renames a file, overwriting the destination if it exists.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @return bool True on success, false on failure.
     * @throws FileExistsException
     * @throws FileNotFoundException
     */
    public function forceRename(string $path, string $newpath)
    {
        $deleted = true;
        if ($this->has($newpath)) {
            $deleted = $this->delete($newpath);
        }

        if ($deleted) {
            return $this->rename($path, $newpath);
        }

        return false;
    }

    /**
     * Get metadata for an object with required metadata.
     *
     * @param string $path     path to file
     * @param array  $metadata metadata keys
     *
     * @return array|false metadata
     * @throws FileNotFoundException
     */
    public function getWithMetadata(string $path, array $metadata)
    {
        $object = $this->getMetadata($path);

        if (!$object) {
            return false;
        }

        $keys = \array_diff($metadata, \array_keys($object));

        foreach ($keys as $key) {
            if (!\method_exists($this, $method = 'get' . \ucfirst($key))) {
                throw new \InvalidArgumentException('Could not fetch metadata: ' . $key);
            }

            $object[$key] = $this->{$method}($path);
        }

        return $object;
    }

    /**
     * List contents with metadata.
     *
     * @param array  $keys
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array listing with metadata
     */
    public function listWith(array $keys = [], $directory = '', $recursive = false)
    {
        $contents = $this->listContents($directory, $recursive);

        foreach ($contents as $index => $object) {
            if ($object['type'] === 'file') {
                $missingKeys = \array_diff($keys, \array_keys($object));
                $contents[$index] = \array_reduce($missingKeys, [$this, 'getMetadataByName'], $object);
            }
        }

        return $contents;
    }

    /**
     * Get a meta-data value by key name.
     *
     * @param array $object
     * @param       $key
     *
     * @return array
     */
    protected function getMetadataByName(array $object, $key)
    {
        $method = 'get' . \ucfirst($key);

        if (!\method_exists($this, $method)) {
            throw new \InvalidArgumentException('Could not get meta-data for key: ' . $key);
        }

        $object[$key] = $this->{$method}($object['path']);

        return $object;
    }

    /**
     * Assert a file is present.
     *
     * @param string $path path to file
     *
     * @throws FileNotFoundException
     *
     * @return void
     */
    public function assertPresent($path)
    {
        if ($this->getConfig('disable_asserts', false) === false && !$this->has($path)) {
            throw new FileNotFoundException($path);
        }
    }

    /**
     * Assert a file is absent.
     *
     * @param string $path path to file
     *
     * @throws FileExistsException
     *
     * @return void
     */
    public function assertAbsent($path)
    {
        if ($this->getConfig('disable_asserts', false) === false && $this->has($path)) {
            throw new FileExistsException($path);
        }
    }
}
