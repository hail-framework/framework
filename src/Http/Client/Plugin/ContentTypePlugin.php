<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Allow to set the correct content type header on the request automatically only if it is not set.
 *
 * @author Karim Pinchon <karim.pinchon@gmail.com>
 */
final class ContentTypePlugin implements PluginInterface
{
    /**
     * Allow to disable the content type detection when stream is too large (as it can consume a lot of resource).
     *
     * @var bool
     *
     * true     skip the content type detection
     * false    detect the content type (default value)
     */
    private $skipDetection = false;

    /**
     * Determine the size stream limit for which the detection as to be skipped (default to 16Mb).
     *
     * @var int
     */
    private $sizeLimit = 16000000;

    /**
     * @param array $config         {
     *
     * @var bool    $skip_detection True skip detection if stream size is bigger than $size_limit.
     * @var int     $size_limit     size stream limit for which the detection as to be skipped.
     * }
     */
    public function __construct(array $config = [])
    {
        if (isset($config['skip_detection'])) {
            $this->skipDetection = (bool) $config['skip_detection'];
        }

        if (isset($config['size_limit']) && \is_int($config['size_limit'])) {
            $this->sizeLimit = $config['size_limit'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        if (!$request->hasHeader('Content-Type')) {
            $stream = $request->getBody();
            $streamSize = $stream->getSize();

            if (!$stream->isSeekable()) {
                return $handler->handle($request);
            }

            if (0 === $streamSize) {
                return $handler->handle($request);
            }

            if ($this->skipDetection && (null === $streamSize || $streamSize >= $this->sizeLimit)) {
                return $handler->handle($request);
            }

            if ($this->isJson($stream)) {
                $request = $request->withHeader('Content-Type', 'application/json');

                return $handler->handle($request);
            }

            if ($this->isXml($stream)) {
                $request = $request->withHeader('Content-Type', 'application/xml');

                return $handler->handle($request);
            }
        }

        return $handler->handle($request);
    }

    /**
     * @param $stream StreamInterface
     *
     * @return bool
     */
    private function isJson($stream)
    {
        $stream->rewind();

        \json_decode($stream->getContents());

        return JSON_ERROR_NONE === \json_last_error();
    }

    /**
     * @param $stream StreamInterface
     *
     * @return \SimpleXMLElement|false
     */
    private function isXml($stream)
    {
        $stream->rewind();

        $previousValue = \libxml_use_internal_errors(true);
        $isXml = \simplexml_load_string($stream->getContents());
        \libxml_use_internal_errors($previousValue);

        return $isXml;
    }
}
