<?php

namespace Hail\Http\Client\Message\Encoding;

use Psr\Http\Message\StreamInterface;

/**
 * Decorates a stream.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
trait StreamTrait
{
    /**
     * @var StreamInterface
     */
    protected $stream;

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->stream->__toString();
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->stream->close();
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        return $this->stream->detach();
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return $this->stream->getSize();
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return $this->stream->tell();
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        return $this->stream->eof();
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable()
    {
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        throw new \RuntimeException('Cannot rewind a filtered stream');
    }
    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        throw new \RuntimeException('Cannot seek a filtered stream');
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return $this->stream->isWritable();
    }

    /**
     * {@inheritdoc}
     */
    public function write($string)
    {
        return $this->stream->write($string);
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return $this->stream->isReadable();
    }

    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        return $this->stream->read($length);
    }

    /**
     * {@inheritdoc}
     */
    public function getContents()
    {
        return $this->stream->getContents();
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        return $this->stream->getMetadata($key);
    }
}
