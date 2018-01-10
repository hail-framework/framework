<?php

namespace Hail\Image;

!\defined('IMAGICK_EXTENSION') || \define('IMAGICK_EXTENSION', \extension_loaded('imagick') && \class_exists('Imagick', false));
!\defined('GD_EXTENSION') || \define('GD_EXTENSION', \extension_loaded('gd') && \function_exists('gd_info'));

use Closure;
use Hail\Factory\Cache;
use Hail\Image\Exception\MissingDependencyException;
use Hail\Image\Exception\NotSupportedException;

class ImageManager
{
    /**
     * @var string
     */
    protected $driver;

    /**
     * @var AbstractDriver
     */
    protected $instance;

    /**
     * @var array
     */
    protected $cacheConfig;

    /**
     * @var ImageCache
     */
    protected $imageCache;

    /**
     * Creates new instance of Image Manager
     *
     * @param array $config
     *
     * @throws MissingDependencyException
     */
    public function __construct(array $config = [])
    {
        if (!\function_exists('finfo_buffer')) {
            throw new MissingDependencyException(
                'PHP Fileinfo extension must be installed/enabled to use Intervention Image.'
            );
        }

        if (isset($config['driver'])) {
            $this->setDriver($config['driver']);
        }

        if (isset($config['cache'])) {
            $this->cacheConfig = $config['cache'];
        }
    }

    /**
     * @param string $driver
     *
     * @return self
     */
    public function setDriver(string $driver = null)
    {
        if ($driver) {
            $this->driver = \strtolower($driver);
        }
        $this->instance = null;

        return $this;
    }

    /**
     * Initiates an Image instance from different input types
     *
     * @param  mixed $data
     *
     * @return \Hail\Image\Image
     */
    public function make($data)
    {
        $driver = $this->instance ?? $this->createDriver();

        return $driver->init($data);
    }

    /**
     * Creates an empty image canvas
     *
     * @param  integer $width
     * @param  integer $height
     * @param  mixed   $background
     *
     * @return \Hail\Image\Image
     */
    public function canvas($width, $height, $background = null)
    {
        $driver = $this->instance ?? $this->createDriver();

        return $driver->newImage($width, $height, $background);
    }

    /**
     * Create new cached image and run callback
     *
     * @param Closure $callback
     * @param int $lifetime
     * @param boolean $returnObj
     *
     * @return Image
     * @throws MissingDependencyException
     */
    public function cache(Closure $callback, $lifetime = null, $returnObj = false)
    {
        if ($this->imageCache === null) {
            // create imagecache
            $this->imageCache = new ImageCache($this);
        }

        // run callback
        if (\is_callable($callback)) {
            $callback($this->imageCache);
        }

        return $this->imageCache->get($lifetime, $returnObj);
    }

    public function buildCache()
    {
        $config = $this->cacheConfig;

        if (empty($config)) {
            throw new \RuntimeException('Not defined image cache config');
        }

        return Cache::simple($config);
    }

    /**
     * Creates a driver instance according to config settings
     *
     * @return \Hail\Image\AbstractDriver
     * @throws NotSupportedException
     */
    private function createDriver()
    {
        switch ($this->driver) {
            case 'imagick':
                if (!IMAGICK_EXTENSION) {
                    throw new NotSupportedException('ImageMagick module not available with this PHP installation.');
                }

                $class = Imagick\Driver::class;
                break;

            case 'gd':
                if (!GD_EXTENSION) {
                    throw new NotSupportedException('GD Library extension not available with this PHP installation.');
                }

                $class = Gd\Driver::class;
                break;

            default:
                if (IMAGICK_EXTENSION) {
                    $class = Imagick\Driver::class;
                } elseif (GD_EXTENSION) {
                    $class = Gd\Driver::class;
                } else {
                    throw new NotSupportedException('Unknown driver type.');
                }
        }

        return $this->instance = new $class;
    }
}
