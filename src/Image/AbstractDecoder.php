<?php

namespace Hail\Image;

use Hail\Http\Factory;
use Hail\Util\MimeType;
use Psr\Http\Message\StreamInterface;

abstract class AbstractDecoder
{
    /**
     * Initiates new image from path in filesystem
     *
     * @param  string $path
     *
     * @return \Hail\Image\Image
     */
    abstract public function initFromPath($path);

    /**
     * Initiates new image from binary data
     *
     * @param  string $data
     *
     * @return \Hail\Image\Image
     */
    abstract public function initFromBinary($data);

    /**
     * Initiates new image from GD resource
     *
     * @param  Resource $resource
     *
     * @return \Hail\Image\Image
     */
    abstract public function initFromGdResource($resource);

    /**
     * Initiates new image from Imagick object
     *
     * @param \Imagick $object
     *
     * @return \Hail\Image\Image
     */
    abstract public function initFromImagick(\Imagick $object);

    /**
     * Buffer of input data
     *
     * @var mixed
     */
    private $data;

    /**
     * Creates new Decoder with data
     *
     * @param mixed $data
     */
    public function __construct($data = null)
    {
        $this->data = $data;
    }

    /**
     * Init from given URL
     *
     * @param  string $url
     *
     * @return \Hail\Image\Image
     */
    public function initFromUrl($url)
    {

        $options = [
            'http' => [
                'method' => "GET",
                'header' => "Accept-language: en\r\n" .
                    "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.2 (KHTML, like Gecko) Chrome/22.0.1216.0 Safari/537.2\r\n",
            ],
        ];

        $context = stream_context_create($options);


        if ($data = @file_get_contents($url, false, $context)) {
            return $this->initFromBinary($data);
        }

        throw new Exception\NotReadableException(
            "Unable to init from given url (" . $url . ")."
        );
    }

    /**
     * Init from given stream
     *
     * @param StreamInterface|resource $stream
     *
     * @return \Hail\Image\Image
     */
    public function initFromStream($stream)
    {
        $stream = Factory::stream($stream);

        try {
            $offset = $stream->tell();
        } catch (\RuntimeException $e) {
            $offset = 0;
        }

        $shouldAndCanSeek = $offset !== 0 && $stream->isSeekable();

        if ($shouldAndCanSeek) {
            $stream->rewind();
        }

        try {
            $data = $stream->getContents();
        } catch (\RuntimeException $e) {
            $data = null;
        }

        if ($shouldAndCanSeek) {
            $stream->seek($offset);
        }

        if ($data) {
            return $this->initFromBinary($data);
        }

        throw new Exception\NotReadableException(
            "Unable to init from given stream"
        );
    }

    /**
     * Determines if current source data is GD resource
     *
     * @return bool
     */
    public function isGdResource()
    {
        if (is_resource($this->data)) {
            return (get_resource_type($this->data) === 'gd');
        }

        return false;
    }

    /**
     * Determines if current source data is Imagick object
     *
     * @return bool
     */
    public function isImagick()
    {
        return $this->data instanceof \Imagick;
    }

    /**
     * Determines if current source data is Hail\Image\Image object
     *
     * @return bool
     */
    public function isHailImage()
    {
        return $this->data instanceof Image;
    }

    /**
     * Determines if current data is SplFileInfo object
     *
     * @return bool
     */
    public function isSplFileInfo()
    {
        return $this->data instanceof \SplFileInfo;
    }

    /**
     * Determines if current source data is file path
     *
     * @return bool
     */
    public function isFilePath()
    {
        if (is_string($this->data)) {
            return @is_file($this->data);
        }

        return false;
    }

    /**
     * Determines if current source data is url
     *
     * @return bool
     */
    public function isUrl()
    {
        return (bool) filter_var($this->data, FILTER_VALIDATE_URL);
    }

    /**
     * Determines if current source data is a stream resource
     *
     * @return bool
     */
    public function isStream()
    {
        if ($this->data instanceof StreamInterface) {
            return true;
        }

        if (!is_resource($this->data)) {
            return false;
        }

        if (get_resource_type($this->data) !== 'stream') {
            return false;
        }

        return true;
    }

    /**
     * Determines if current source data is binary data
     *
     * @return bool
     */
    public function isBinary()
    {
        if (is_string($this->data)) {
            $mime = MimeType::getMimeTypeByContent($this->data);

            return (strpos($mime, 'text') !== 0 && $mime !== 'application/x-empty');
        }

        return false;
    }

    /**
     * Determines if current source data is data-url
     *
     * @return bool
     */
    public function isDataUrl()
    {
        $data = $this->decodeDataUrl($this->data);

        return null !== $data;
    }

    /**
     * Determines if current source data is base64 encoded
     *
     * @return bool
     */
    public function isBase64()
    {
        if (!is_string($this->data)) {
            return false;
        }

        return base64_encode(base64_decode($this->data)) === $this->data;
    }

    /**
     * Parses and decodes binary image data from data-url
     *
     * @param  string $data_url
     *
     * @return string
     */
    private function decodeDataUrl($data_url)
    {
        if (!is_string($data_url)) {
            return null;
        }

        $pattern = "/^data:(?:image\/[a-zA-Z\-\.]+)(?:charset=\".+\")?;base64,(?P<data>.+)$/";
        preg_match($pattern, $data_url, $matches);

        if (is_array($matches) && array_key_exists('data', $matches)) {
            return base64_decode($matches['data']);
        }

        return null;
    }

    /**
     * Initiates new image from mixed data
     *
     * @param  mixed $data
     *
     * @return \Hail\Image\Image
     */
    public function init($data)
    {
        $this->data = $data;

        switch (true) {

            case $this->isGdResource():
                return $this->initFromGdResource($this->data);

            case $this->isImagick():
                return $this->initFromImagick($this->data);

            case $this->isHailImage():
                return $this->data;

            case $this->isSplFileInfo():
                return $this->initFromPath($this->data->getRealPath());

            case $this->isBinary():
                return $this->initFromBinary($this->data);

            case $this->isUrl():
                return $this->initFromUrl($this->data);

            case $this->isStream():
                return $this->initFromStream($this->data);

            case $this->isDataUrl():
                return $this->initFromBinary($this->decodeDataUrl($this->data));

            case $this->isFilePath():
                return $this->initFromPath($this->data);

            // isBase64 has to be after isFilePath to prevent false positives
            case $this->isBase64():
                return $this->initFromBinary(base64_decode($this->data));

            default:
                throw new Exception\NotReadableException("Image source not readable");
        }
    }

    /**
     * Decoder object transforms to string source data
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->data;
    }
}
