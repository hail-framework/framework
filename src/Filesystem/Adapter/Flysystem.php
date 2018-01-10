<?php

namespace Hail\Filesystem\Adapter;

use Hail\Filesystem\AdapterInterface;
use League\Flysystem\AdapterInterface as FlyAdapter;
use League\Flysystem\Config;
use League\Flysystem\Adapter\CanOverwriteFiles;

/**
 * Class FlySystem
 *
 * @package Hail\Filesystem\Adapter
 */
class Flysystem implements AdapterInterface
{
    /**
     * @var FlyAdapter
     */
    protected $adapter;

    protected $config;

    public function __construct(FlyAdapter $adapter)
    {
        $this->adapter = $adapter;
    }

    public function has($path)
    {
        return $this->adapter->has($path);
    }

    public function read($path)
    {
        return $this->adapter->read($path);
    }

    public function readStream($path)
    {
        return $this->adapter->readStream($path);
    }

    public function listContents($directory = '', $recursive = false)
    {
        return $this->adapter->listContents($directory, $recursive);
    }

    public function getMetadata($path)
    {
        return $this->adapter->getMetadata($path);
    }

    public function getSize($path)
    {
        return $this->adapter->getSize($path);
    }

    public function getMimetype($path)
    {
        return $this->adapter->getMimetype($path);
    }

    public function getTimestamp($path)
    {
        return $this->adapter->getTimestamp($path);
    }

    public function getVisibility($path)
    {
        return $this->adapter->getVisibility($path);
    }

    public function rename($path, $newpath)
    {
        return $this->adapter->rename($path);
    }

    public function copy($path, $newpath)
    {
        return $this->adapter->copy($path);
    }

    public function delete($path)
    {
        return $this->adapter->delete($path);
    }

    public function deleteDir($dirname)
    {
        return $this->adapter->delete($dirname);
    }

    public function setVisibility($path, $visibility)
    {
        return $this->adapter->setVisibility($path, $visibility);
    }

    public function write($path, $contents, array $config)
    {
        return $this->adapter->write($path, $contents, new Config($config));
    }

    public function writeStream($path, $resource, array $config)
    {
        return $this->adapter->writeStream($path, $resource, new Config($config));
    }

    public function update($path, $contents, array $config)
    {
        return $this->adapter->update($path, $contents, new Config($config));
    }

    public function updateStream($path, $resource, array $config)
    {
        return $this->adapter->updateStream($path, $resource, new Config($config));
    }

    public function createDir($dirname, array $config)
    {
        return $this->adapter->createDir($dirname, new Config($config));
    }

    public function canOverwrite()
    {
        return $this instanceof CanOverwriteFiles;
    }
}
