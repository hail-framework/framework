<?php

namespace Hail\Http\Client\Message\Encoding;

use Hail\Util\StreamFilter;
use Psr\Http\Message\StreamInterface;

/**
 * A filtered stream has a filter for filtering output and a filter for filtering input made to a underlying stream.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
abstract class FilteredStream implements StreamInterface
{
    protected const BUFFER_SIZE = 8192;

    use StreamTrait;

    /**
     * @var callable
     */
    protected $readFilterCallback;

    /**
     * Internal buffer.
     *
     * @var string
     */
    protected $buffer = '';

    /**
     * @param StreamInterface $stream
     * @param mixed|null      $readFilterOptions
     */
    public function __construct(StreamInterface $stream, $readFilterOptions = null)
    {
        $this->readFilterCallback = StreamFilter::create($this->readFilter(), $readFilterOptions);
        $this->stream = $stream;
    }


    /**
     * {@inheritdoc}
     */
    public function read($length)
    {
        if (\strlen($this->buffer) >= $length) {
            $read = \substr($this->buffer, 0, $length);
            $this->buffer = \substr($this->buffer, $length);

            return $read;
        }

        if ($this->stream->eof()) {
            $buffer = $this->buffer;
            $this->buffer = '';

            return $buffer;
        }

        $read = $this->buffer;
        $this->buffer = '';
        $this->fill();

        return $read . $this->read($length - \strlen($read));
    }

    /**
     * {@inheritdoc}
     */
    public function eof()
    {
        return $this->stream->eof() && '' === $this->buffer;
    }

    /**
     * Buffer is filled by reading underlying stream.
     *
     * Callback is reading once more even if the stream is ended.
     * This allow to get last data in the PHP buffer otherwise this
     * bug is present : https://bugs.php.net/bug.php?id=48725
     */
    protected function fill()
    {
        $readFilterCallback = $this->readFilterCallback;
        $this->buffer .= $readFilterCallback($this->stream->read(self::BUFFER_SIZE));

        if ($this->stream->eof()) {
            $this->buffer .= $readFilterCallback();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContents()
    {
        $buffer = '';

        while (!$this->eof()) {
            $buf = $this->read(self::BUFFER_SIZE);
            // Using a loose equality here to match on '' and false.
            if (null === $buf) {
                break;
            }

            $buffer .= $buf;
        }

        return $buffer;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->getContents();
    }

    /**
     * Returns the write filter name.
     *
     * @return string
     */
    abstract protected function readFilter();

    /**
     * Returns the write filter name.
     *
     * @return string
     */
    abstract protected function writeFilter();
}
