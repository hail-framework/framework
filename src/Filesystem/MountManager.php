<?php

namespace Hail\Filesystem;

use Hail\Filesystem\Exception\{FileExistsException,
    FileNotFoundException,
    FilesystemNotFoundException,
    RootViolationException};


/**
 * Class MountManager.
 *
 * Proxies methods to Filesystem (@see __call):
 *
 * @method AdapterInterface getAdapter($prefix)
 * @method array getConfig($prefix)
 * @method array getWithMetadata($path, array $metadata)
 * @method void assertPresent($path)
 * @method void assertAbsent($path)
 */
class MountManager implements FilesystemInterface
{
    use PluginTrait;

    /**
     * @var FilesystemInterface[]
     */
    protected $filesystems = [];

    /**
     * @var array
     */
    protected $lazy = [];

    /**
     * Constructor.
     *
     * @param FilesystemInterface[] $filesystems [:prefix => Filesystem,]
     */
    public function __construct(array $filesystems = [])
    {
        $this->mountFilesystems($filesystems);
    }

    /**
     * Mount filesystems.
     *
     * @param FilesystemInterface[] $filesystems [:prefix => Filesystem,]
     *
     * @return $this
     */
    public function mountFilesystems(array $filesystems)
    {
        foreach ($filesystems as $prefix => $filesystem) {
            $this->mountFilesystem($prefix, $filesystem);
        }

        return $this;
    }

    /**
     * Mount filesystems.
     *
     * @param string           $prefix
     * @param array|Filesystem $filesystem
     *
     * @return $this
     */
    public function mountFilesystem(string $prefix, $filesystem)
    {
        if (\is_array($filesystem)) {
            $this->lazy[$prefix] = $filesystem;
        } else {
            $this->filesystems[$prefix] = $filesystem;
        }

        return $this;
    }

    /**
     * Get the filesystem with the corresponding prefix.
     *
     * @param string $prefix
     *
     * @return FilesystemInterface
     * @throws FilesystemNotFoundException
     */
    public function getFilesystem(string $prefix)
    {
        if (!isset($this->filesystems[$prefix])) {
            if (isset($this->lazy[$prefix])) {
                return $this->filesystems[$prefix] = new Filesystem($this->lazy[$prefix]);
            }

            throw new FilesystemNotFoundException('No filesystem mounted with prefix ' . $prefix);
        }

        return $this->filesystems[$prefix];
    }

    /**
     * Retrieve the prefix from an arguments array.
     *
     * @param array $arguments
     *
     * @return array [:prefix, :arguments]
     */
    public function filterPrefix(array $arguments)
    {
        if (empty($arguments)) {
            throw new \InvalidArgumentException('At least one argument needed');
        }

        $path = &$arguments[0];

        if (!\is_string($path)) {
            throw new \InvalidArgumentException('First argument should be a string');
        }

        [$prefix, $path] = $this->getPrefixAndPath($path);

        return [$prefix, $arguments];
    }

    /**
     * @param string $directory
     * @param bool   $recursive
     *
     * @return array
     * @throws FilesystemNotFoundException
     */
    public function listContents($directory = '', $recursive = false)
    {
        [$filesystem, $directory, $prefix] = $this->getFilesystemAndPath($directory);
        $result = $filesystem->listContents($directory, $recursive);

        foreach ($result as &$file) {
            $file['filesystem'] = $prefix;
        }

        return $result;
    }

    /**
     * Call forwarder.
     *
     * @param string $method
     * @param array  $arguments
     *
     * @return mixed
     * @throws FilesystemNotFoundException
     * @throws FileExistsException
     */
    public function __call($method, $arguments)
    {
        list($prefix, $args) = $this->filterPrefix($arguments);

        $fs = $this->filesystems[$prefix] ?? $this->getFilesystem($prefix);

        return $fs->$method(...$args);
    }

    /**
     * @param string $from
     * @param string $to
     * @param array  $config
     *
     * @return bool
     * @throws FilesystemNotFoundException
     */
    public function copy($from, $to, array $config = [])
    {
        [$fsFrom, $from] = $this->getFilesystemAndPath($from);
        $buffer = $fsFrom->readStream($from);

        if ($buffer === false) {
            return false;
        }

        [$fsTo, $to] = $this->getFilesystemAndPath($to);
        $result = $fsTo->writeStream($to, $buffer, $config);

        if (\is_resource($buffer)) {
            \fclose($buffer);
        }

        return $result;
    }

    /**
     * List contents with metadata.
     *
     * @param array  $keys
     * @param string $directory
     * @param bool   $recursive
     *
     * @return mixed
     */
    public function listWith(array $keys = [], $directory = '', $recursive = false)
    {
        [$fs, $directory] = $this->getFilesystemAndPath($directory);

        return $fs->listWith($keys, $directory, $recursive);
    }

    /**
     * Empty a directory's contents.
     *
     * @param string $directory
     */
    public function emptyDir($directory)
    {
        [$fs, $directory] = $this->getFilesystemAndPath($directory);

        $fs->emptyDir($directory);
    }

    /**
     * Move a file.
     *
     * @param       $from
     * @param       $to
     * @param array $config
     *
     * @return bool
     * @throws FilesystemNotFoundException
     * @throws Exception\FileExistsException
     * @throws FileNotFoundException
     */
    public function move($from, $to, array $config = [])
    {
        [$prefixFrom, $pathFrom] = $this->getPrefixAndPath($from);
        [$prefixTo, $pathTo] = $this->getPrefixAndPath($to);

        if ($prefixFrom === $prefixTo) {
            $filesystem = $this->filesystems[$prefixFrom] ?? $this->getFilesystem($prefixFrom);
            $renamed = $filesystem->rename($pathFrom, $pathTo);

            if ($renamed && isset($config['visibility'])) {
                return $filesystem->setVisibility($pathTo, $config['visibility']);
            }

            return $renamed;
        }

        $copied = $this->copy($from, $to, $config);

        if ($copied) {
            return $this->delete($from);
        }

        return false;
    }

    /**
     * Renames a file, overwriting the destination if it exists.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @return bool True on success, false on failure.
     * @throws FileNotFoundException Thrown if $path does not exist.
     * @throws FilesystemNotFoundException
     * @throws FileExistsException
     */
    public function forceRename(string $path, string $newpath)
    {
        $deleted = true;
        if ($this->has($newpath)) {
            try {
                $deleted = $this->delete($newpath);
            } catch (FileNotFoundException $e) {
                // The destination path does not exist. That's ok.
                $deleted = true;
            }
        }

        if ($deleted) {
            return $this->move($path, $newpath);
        }

        return false;
    }

    /**
     * @param string $path
     *
     * @return string[] [:prefix, :path]
     */
    protected function getPrefixAndPath($path)
    {
        if (strpos($path, '://') < 1) {
            throw new \InvalidArgumentException('No prefix detected in path: ' . $path);
        }

        return \explode('://', $path, 2);
    }

    protected function getFilesystemAndPath($directory)
    {
        [$prefix, $directory] = $this->getPrefixAndPath($directory);
        $filesystem = $this->filesystems[$prefix] ?? $this->getFilesystem($prefix);

        return [$filesystem, $directory, $prefix];
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
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->has($path);
    }

    /**
     * Read a file.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return string|false The file contents or false on failure.
     */
    public function read($path)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->read($path);
    }

    /**
     * Retrieves a read-stream for a path.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return resource|false The path resource or false on failure.
     */
    public function readStream($path)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->readStream($path);
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
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->getMetadata($path);
    }

    /**
     * Get a file's size.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return int|false The file size or false on failure.
     */
    public function getSize($path)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->getSize($path);
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
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->getMimetype($path);
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
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->getTimestamp($path);
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
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->getVisibility($path);
    }

    /**
     * Write a new file.
     *
     * @param string $path     The path of the new file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @throws FileExistsException
     *
     * @return bool True on success, false on failure.
     */
    public function write($path, $contents, array $config = [])
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->write($path, $contents, $config);
    }

    /**
     * Write a new file using a stream.
     *
     * @param string   $path     The path of the new file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @throws \InvalidArgumentException If $resource is not a file handle.
     * @throws FileExistsException
     *
     * @return bool True on success, false on failure.
     */
    public function writeStream($path, $resource, array $config = [])
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->writeStream($path, $resource, $config);
    }

    /**
     * Update an existing file.
     *
     * @param string $path     The path of the existing file.
     * @param string $contents The file contents.
     * @param array  $config   An optional configuration array.
     *
     * @throws FileNotFoundException
     *
     * @return bool True on success, false on failure.
     */
    public function update($path, $contents, array $config = [])
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->update($path, $contents, $config);
    }

    /**
     * Update an existing file using a stream.
     *
     * @param string   $path     The path of the existing file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @throws \InvalidArgumentException If $resource is not a file handle.
     * @throws FileNotFoundException
     *
     * @return bool True on success, false on failure.
     */
    public function updateStream($path, $resource, array $config = [])
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->updateStream($path, $resource, $config);
    }

    /**
     * Rename a file.
     *
     * @param string $path    Path to the existing file.
     * @param string $newpath The new path of the file.
     *
     * @throws FileExistsException   Thrown if $newpath exists.
     * @throws FileNotFoundException Thrown if $path does not exist.
     *
     * @return bool True on success, false on failure.
     */
    public function rename($path, $newpath)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->rename($path, $newpath);
    }

    /**
     * Delete a file.
     *
     * @param string $path
     *
     * @throws FileNotFoundException
     *
     * @return bool True on success, false on failure.
     */
    public function delete($path)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->delete($path);
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
        list($prefix, $dirname) = $this->getPrefixAndPath($dirname);

        return $this->getFilesystem($prefix)->deleteDir($dirname);
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
        list($prefix, $dirname) = $this->getPrefixAndPath($dirname);

        return $this->getFilesystem($prefix)->createDir($dirname);
    }

    /**
     * Set the visibility for a file.
     *
     * @param string $path       The path to the file.
     * @param string $visibility One of 'public' or 'private'.
     *
     * @throws FileNotFoundException
     *
     * @return bool True on success, false on failure.
     */
    public function setVisibility($path, $visibility)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->setVisibility($path, $visibility);
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
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->put($path, $contents, $config);
    }

    /**
     * Create a file or update if exists.
     *
     * @param string   $path     The path to the file.
     * @param resource $resource The file handle.
     * @param array    $config   An optional configuration array.
     *
     * @throws \InvalidArgumentException Thrown if $resource is not a resource.
     *
     * @return bool True on success, false on failure.
     */
    public function putStream($path, $resource, array $config = [])
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->putStream($path, $resource, $config);
    }

    /**
     * Read and delete a file.
     *
     * @param string $path The path to the file.
     *
     * @throws FileNotFoundException
     *
     * @return string|false The file contents, or false on failure.
     */
    public function readAndDelete($path)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->readAndDelete($path);
    }

    /**
     * Get a file/directory handler.
     *
     * @deprecated
     *
     * @param string  $path    The path to the file.
     * @param Handler $handler An optional existing handler to populate.
     *
     * @return Handler Either a file or directory handler.
     */
    public function get($path, Handler $handler = null)
    {
        [$prefix, $path] = $this->getPrefixAndPath($path);

        return $this->getFilesystem($prefix)->get($path);
    }
}
