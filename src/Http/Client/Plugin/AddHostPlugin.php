<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Add schema, host and port to a request. Can be set to overwrite the schema and host if desired.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
final class AddHostPlugin implements PluginInterface
{
    /**
     * @var UriInterface
     */
    private $host;

    /**
     * @var bool
     */
    private $replace = false;

    /**
     * @param UriInterface $host
     * @param array        $config {
     *
     *     @var bool $replace True will replace all hosts, false will only add host when none is specified.
     * }
     */
    public function __construct(UriInterface $host, array $config = [])
    {
        if ('' === $host->getHost()) {
            throw new \LogicException('Host can not be empty');
        }

        $this->host = $host;

        if (isset($config['replace'])) {
            $this->replace = (bool) $config['replace'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        if ($this->replace || '' === $request->getUri()->getHost()) {
            $uri = $request->getUri()
                ->withHost($this->host->getHost())
                ->withScheme($this->host->getScheme())
                ->withPort($this->host->getPort())
            ;

            $request = $request->withUri($uri);
        }

        return $handler->handle($request);
    }
}
