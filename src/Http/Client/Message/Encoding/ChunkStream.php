<?php

namespace Hail\Http\Client\Message\Encoding;

use Psr\Http\Message\StreamInterface;

/**
 * Transform a regular stream into a chunked one.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class ChunkStream extends FilteredStream
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
        return 'chunk';
    }

    /**
     * {@inheritdoc}
     */
    protected function writeFilter()
    {
        return 'dechunk';
    }

    /**
     * {@inheritdoc}
     */
    protected function fill()
    {
        parent::fill();

        if ($this->stream->eof()) {
            $this->buffer .= "0\r\n\r\n";
        }
    }
}
