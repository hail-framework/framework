<?php

namespace Hail\Http\Client\Message\Encoding;

use Psr\Http\Message\StreamInterface;

/**
 * Decorate a stream which is chunked.
 *
 * Allow to decode a chunked stream
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class DechunkStream extends FilteredStream
{
    public function __construct(StreamInterface $stream, $readFilterOptions = null)
    {
        // Register chunk filter if not found
        if (!\array_key_exists('chunk', \stream_get_filters())) {
            \stream_filter_register('chunk', Filter\Chunk::class);
        }

        parent::__construct($stream, $readFilterOptions);
    }

    /**
     * {@inheritdoc}
     */
    protected function readFilter()
    {
        return 'dechunk';
    }

    /**
     * {@inheritdoc}
     */
    protected function writeFilter()
    {
        return 'chunk';
    }
}
