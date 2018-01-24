<?php

namespace Hail\Http\Middleware;

use Hail\Http\Middleware\Util\{
    NegotiatorTrait, FileFormats
};
use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
    Message\ServerRequestInterface,
    Message\ResponseInterface
};

class ContentType implements MiddlewareInterface
{
    use NegotiatorTrait;

    /**
     * @var string Default format
     */
    private $default = 'html';

    /**
     * @var array Available formats with the mime types
     */
    private $formats;

    /**
     * @var bool Include X-Content-Type-Options: nosniff
     */
    private $nosniff = true;

    /**
     * Define de available formats.
     *
     * @param array|null $formats
     */
    public function __construct(array $formats = null)
    {
        $this->formats = $formats ?: FileFormats::get();
    }

    /**
     * Set the default format.
     *
     * @param string $format
     *
     * @return self
     */
    public function defaultFormat($format)
    {
        $this->default = $format;

        return $this;
    }

    /**
     * Configure the nosniff option.
     *
     * @param bool $nosniff
     *
     * @return self
     */
    public function nosniff($nosniff = true)
    {
        $this->nosniff = $nosniff;

        return $this;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface      $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $format = $this->detectFromExtension($request) ?? $this->detectFromHeader($request) ?? $this->default;

        //If no valid accept type was found, and no default was specified, then return 406 Not Acceptable.
        if (null === $format) {
            $response = $handler->handle($request);
            return $response->withStatus(406);
        }

        $contentType = $this->formats[$format]['mime-type'][0];

        $request = $request
            ->withHeader('Accept', $contentType)
            ->withHeader('Accept-Charset', 'UTF-8');

        $response = $handler->handle($request);

        if (!$response->hasHeader('Content-Type')) {
            $needCharset = !empty($this->formats[$format]['charset']);

            if ($needCharset) {
                $contentType .= '; charset=UTF-8';
            }

            $response = $response->withHeader('Content-Type', $contentType);
        }

        if ($this->nosniff && !$response->hasHeader('X-Content-Type-Options')) {
            $response = $response->withHeader('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }

    /**
     * Returns the format using the file extension.
     *
     * @return null|string
     */
    private function detectFromExtension(ServerRequestInterface $request)
    {
        $extension = \strtolower(\pathinfo($request->getUri()->getPath(), PATHINFO_EXTENSION));

        if (empty($extension)) {
            return null;
        }

        foreach ($this->formats as $format => $data) {
            if (\in_array($extension, $data['extension'], true)) {
                return $format;
            }
        }

        return null;
    }

    /**
     * Returns the format using the Accept header.
     *
     * @return null|string
     */
    private function detectFromHeader(ServerRequestInterface $request)
    {
        $mimes = \array_column($this->formats, 'mime-type');
        $headers = \array_merge(...$mimes);

        $accept = $request->getHeaderLine('Accept');
        $mime = $this->getBest($accept, $headers);

        if ($mime !== null) {
            foreach ($this->formats as $format => $data) {
                if (\in_array($mime, $data['mime-type'], true)) {
                    return $format;
                }
            }
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function pareseAccept($accept): array
    {
        $accept = self::normalPareseAccept($accept);

        $type = $accept['type'];
        if ($type === '*') {
            $type = '*/*';
        }

        $parts = \explode('/', $type);

        if (\count($parts) !== 2 || !$parts[0] || !$parts[1]) {
            throw new \RuntimeException('Accept header parse failed: ' . $type);
        }

        $accept['type'] = $type;
        $accept['basePart'] = $parts[0];
        $accept['subPart'] = $parts[1];

        return $accept;
    }

    /**
     * {@inheritdoc}
     */
    protected function match(array $accept, array $priority, $index)
    {
        $ab = $accept['basePart'];
        $pb = $priority['basePart'];

        $as = $accept['subPart'];
        $ps = $priority['subPart'];

        $intersection = \array_intersect_assoc($accept['parameters'], $priority['parameters']);

        $baseEqual = !\strcasecmp($ab, $pb);
        $subEqual = !\strcasecmp($as, $ps);

        if (($ab === '*' || $baseEqual) && ($as === '*' || $subEqual) && \count($intersection) === \count($accept['parameters'])) {
            $score = 100 * $baseEqual + 10 * $subEqual + \count($intersection);

            return [
                'quality' => $accept['quality'] * $priority['quality'],
                'score' => $score,
                'index' => $index,
            ];
        }

        return null;
    }
}