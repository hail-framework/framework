<?php

namespace Hail\Filesystem\Adapter\Polyfill;

use Hail\Filesystem\Util;

trait StreamedWritingTrait
{
    /**
     * Stream fallback delegator.
     *
     * @param string   $path
     * @param resource $resource
     * @param array   $config
     * @param string   $fallback
     *
     * @return mixed fallback result
     */
    protected function stream($path, $resource, array $config, $fallback)
    {
        Util::rewindStream($resource);
        $contents = \stream_get_contents($resource);

        return $this->{$fallback}($path, $contents, $config);
    }

    /**
     * Write using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param array   $config
     *
     * @return mixed false or file metadata
     */
    public function writeStream($path, $resource, array $config)
    {
        return $this->stream($path, $resource, $config, 'write');
    }

    /**
     * Update a file using a stream.
     *
     * @param string   $path
     * @param resource $resource
     * @param array   $config   visibility setting
     *
     * @return mixed false of file metadata
     */
    public function updateStream($path, $resource, array $config)
    {
        return $this->stream($path, $resource, $config, 'update');
    }

    // Required abstract methods
    abstract public function write($pash, $contents, array $config);
    abstract public function update($pash, $contents, array $config);
}
