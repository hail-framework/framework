<?php

namespace Hail\Image;

use Hail\Util\Serialize;
use Psr\SimpleCache\CacheInterface;

class ImageCache
{
    /**
     * History of name and arguments of calls performed on image
     *
     * @var array
     */
    public $calls = [];

    /**
     * Additional properties included in checksum
     *
     * @var array
     */
    public $properties = [];

    /**
     * Processed Image
     *
     * @var Image
     */
    public $image;

    /**
     * Intervention Image Manager
     *
     * @var ImageManager
     */
    public $manager;

    /**
     * @var CacheInterface
     */
    public $cache;

    /**
     * Create a new instance
     *
     * @param ImageManager|array $manager
     */
    public function __construct($manager = null)
    {
        if ($manager instanceof ImageManager) {
            $this->manager = $manager;
        } elseif (\is_array($manager)) {
            $this->manager = new ImageManager($manager);
        }

        $this->cache = $this->manager->buildCache();
    }

    /**
     * Magic method to capture action calls
     *
     * @param  string $name
     * @param  array  $arguments
     *
     * @return self
     */
    public function __call($name, $arguments)
    {
        $this->registerCall($name, $arguments);

        return $this;
    }

    /**
     * Special make method to add modifed data to checksum
     *
     * @param  mixed $data
     *
     * @return self
     */
    public function make($data)
    {
        // include "modified" property for any files
        if ($this->isFile($data)) {
            $this->setProperty('modified', \filemtime((string) $data));
        }

        // register make call
        $this->__call('make', [$data]);

        return $this;
    }

    /**
     * Checks if given data is file, handles mixed input
     *
     * @param  mixed $value
     *
     * @return bool
     */
    protected function isFile($value)
    {
        $value = \str_replace("\0", '', $value);

        return \strlen($value) <= PHP_MAXPATHLEN && \is_file($value);
    }

    /**
     * Set custom property to be included in checksum
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return self
     */
    public function setProperty($key, $value)
    {
        $this->properties[$key] = $value;

        return $this;
    }

    /**
     * Returns checksum of current image state
     *
     * @return string
     */
    public function checksum()
    {
        $properties = Serialize::encode($this->properties);
        $calls = Serialize::encode($this->getSanitizedCalls());

        return \md5($properties . $calls);
    }

    /**
     * Register static call for later use
     *
     * @param  string $name
     * @param  array  $arguments
     *
     * @return void
     */
    protected function registerCall($name, $arguments)
    {
        $this->calls[] = ['name' => $name, 'arguments' => $arguments];
    }

    /**
     * Clears history of calls
     *
     * @return void
     */
    protected function clearCalls()
    {
        $this->calls = [];
    }

    /**
     * Clears all currently set properties
     *
     * @return void
     */
    protected function clearProperties()
    {
        $this->properties = [];
    }

    /**
     * Return unprocessed calls
     *
     * @return array
     */
    protected function getCalls()
    {
        return \count($this->calls) ? $this->calls : [];
    }

    /**
     * Replace Closures in arguments with SerializableClosure
     *
     * @return array
     */
    protected function getSanitizedCalls()
    {
        $calls = $this->getCalls();

        foreach ($calls as &$call) {
            foreach ($call['arguments'] as &$argument) {
                if ($argument instanceof \Closure) {
                    $argument = Serialize::encodeClosure($argument);
                }
            }
        }

        return $calls;
    }

    /**
     * Process call on current image
     *
     * @param  array $call
     *
     * @return void
     */
    protected function processCall($call)
    {
        $this->image = $this->image->{$call['name']}(...$call['arguments']);
    }

    /**
     * Process all saved image calls on Image object
     *
     * @return Image
     */
    public function process()
    {
        // first call on manager
        $this->image = $this->manager;

        // process calls on image
        foreach ($this->getCalls() as $call) {
            $this->processCall($call);
        }

        // append checksum to image
        $this->image->cachekey = $this->checksum();

        // clean-up
        $this->clearCalls();
        $this->clearProperties();

        return $this->image;
    }

    /**
     * Get image either from cache or directly processed
     * and save image in cache if it's not saved yet
     *
     * @param  int  $lifetime
     * @param  bool $returnObj
     *
     * @return mixed
     */
    public function get(int $lifetime = null, bool $returnObj = false)
    {
        $lifetime = $lifetime ?? 300;
        $key = $this->checksum();

        // try to get image from cache
        $cachedImageData = $this->cache->get($key);

        // if imagedata exists in cache
        if ($cachedImageData) {
            // transform into image-object
            if ($returnObj) {
                $image = $this->manager->make($cachedImageData);
                $cachedImage = new CachedImage;

                return $cachedImage->setFromOriginal($image, $key);
            }

            // return raw data
            return $cachedImageData;
        }

        // process image data
        $image = $this->process();

        // encode image data only if image is not encoded yet
        $encoded = $image->encoded ?: (string) $image->encode();

        // save to cache...
        $this->cache->set($key, $encoded, $lifetime);

        // return processed image
        return $returnObj ? $image : $encoded;
    }
}
