<?php

namespace Hail\Http\Client\Message\Encoding;

use Psr\Http\Message\StreamInterface;

!\defined('ZLIB_EXTENSION') && \define('ZLIB_EXTENSION', \extension_loaded('zlib'));

/**
 * Stream decompress (RFC 1950).
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class DecompressStream extends FilteredStream
{
    /**
     * @param StreamInterface $stream
     * @param int             $level
     */
    public function __construct(StreamInterface $stream, $level = -1)
    {
        if (!ZLIB_EXTENSION) {
            throw new \RuntimeException('The zlib extension must be enabled to use this stream');
        }

        parent::__construct($stream, ['window' => 15]);
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
