<?php

namespace Hail\Http\Middleware;

use Hail\Http\Middleware\Util\NegotiatorTrait;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Interop\Http\Server\{
    MiddlewareInterface,
    RequestHandlerInterface
};

class ContentEncoding implements MiddlewareInterface
{
    use NegotiatorTrait;

    /**
     * @var array Available encodings
     */
    private $encodings = [
        'gzip',
        'deflate',
    ];

    /**
     * Define de available encodings.
     *
     * @param array|null $encodings
     */
    public function __construct(array $encodings = null)
    {
        if ($encodings !== null) {
            $this->encodings = $encodings;
        }
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
        if ($request->hasHeader('Accept-Encoding')) {
            $accept = $request->getHeaderLine('Accept-Encoding');

            $encoding = $this->getBest($accept, $this->encodings);

            if ($encoding === null) {
                return $handler->handle($request->withoutHeader('Accept-Encoding'));
            }

            return $handler->handle($request->withHeader('Accept-Encoding', $encoding));
        }

        return $handler->handle($request);
    }

    /**
     * @param array $header
     * @param array $priority
     * @param int      $index
     *
     * @return array|null Headers matched
     */
    protected function match(array $header, array $priority, $index)
    {
        $ac = $header['type'];
        $pc = $priority['type'];

        $equal = !\strcasecmp($ac, $pc);

        if ($equal || $ac === '*') {
            $score = 1 * $equal;

            return [
                'quality' => $header['quality'] * $priority['quality'],
                'score' => $score,
                'index' => $index
            ];
        }

        return null;
    }
}