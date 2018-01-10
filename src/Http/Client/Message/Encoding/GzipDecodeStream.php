<?php

namespace Hail\Http\Client\Message\Encoding;

use Psr\Http\Message\StreamInterface;

!\defined('ZLIB_EXTENSION') && \define('ZLIB_EXTENSION', \extension_loaded('zlib'));

/**
 * Stream for decoding from gzip format (RFC 1952).
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class GzipDecodeStream extends FilteredStream
{
    /**
     * @param StreamInterface $stream
     */
    public function __construct(StreamInterface $stream)
    {
        if (!ZLIB_EXTENSION) {
            throw new \RuntimeException('The zlib extension must be enabled to use this stream');
        }

        parent::__construct($stream, ['window' => 31]);
    }

    /**
     * {@inheritdoc}
     */
    protected function readFilter()
    {
        return 'zlib.inflate';
    }

    /**
     * {@inheritdoc}
     */
    protected function writeFilter()
    {
        return 'zlib.deflate';
    }
}
