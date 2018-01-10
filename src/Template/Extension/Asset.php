<?php

namespace Hail\Template\Extension;

use Hail\Template\Engine;
use Hail\Template\Template;

/**
 * Extension that adds the ability to create "cache busted" asset URLs.
 */
class Asset implements ExtensionInterface
{
    /**
     * Instance of the current template.
     *
     * @var Template
     */
    public $template;

    /**
     * Path to asset directory.
     *
     * @var string
     */
    public $path;

    /**
     * Enables the filename method.
     *
     * @var bool
     */
    public $filenameMethod;

    /**
     * Create new Asset instance.
     *
     * @param string $path
     * @param bool   $filenameMethod
     */
    public function __construct($path, $filenameMethod = false)
    {
        $this->path = \rtrim($path, '/');
        $this->filenameMethod = $filenameMethod;
    }

    /**
     * Register extension function.
     *
     * @param Engine $engine
     *
     * @return null
     */
    public function register(Engine $engine)
    {
        $engine->registerFunction('asset', [$this, 'cachedAssetUrl']);
    }

    /**
     * Create "cache busted" asset URL.
     *
     * @param  string $url
     *
     * @return string
     */
    public function cachedAssetUrl($url)
    {
        $filePath = $this->path . '/' . \ltrim($url, '/');

        if (!\file_exists($filePath)) {
            throw new \LogicException(
                'Unable to locate the asset "' . $url . '" in the "' . $this->path . '" directory.'
            );
        }

        $lastUpdated = \filemtime($filePath);
        $pathInfo = \pathinfo($url);

        if ($pathInfo['dirname'] === '.') {
            $directory = '';
        } elseif ($pathInfo['dirname'] === '/') {
            $directory = '/';
        } else {
            $directory = $pathInfo['dirname'] . '/';
        }

        if ($this->filenameMethod) {
            return $directory . $pathInfo['filename'] . '.' . $lastUpdated . '.' . $pathInfo['extension'];
        }

        return $directory . $pathInfo['filename'] . '.' . $pathInfo['extension'] . '?v=' . $lastUpdated;
    }
}
