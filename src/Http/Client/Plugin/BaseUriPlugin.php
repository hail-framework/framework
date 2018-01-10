<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Combines the AddHostPlugin and AddPathPlugin.
 *
 * @author Sullivan Senechal <soullivaneuh@gmail.com>
 */
final class BaseUriPlugin implements PluginInterface
{
    /**
     * @var AddHostPlugin
     */
    private $addHostPlugin;

    /**
     * @var AddPathPlugin|null
     */
    private $addPathPlugin;

    /**
     * @param UriInterface $uri        Has to contain a host name and cans have a path.
     * @param array        $hostConfig Config for AddHostPlugin. @see AddHostPlugin::configureOptions
     */
    public function __construct(UriInterface $uri, array $hostConfig = [])
    {
        $this->addHostPlugin = new AddHostPlugin($uri, $hostConfig);

        if (rtrim($uri->getPath(), '/')) {
            $this->addPathPlugin = new AddPathPlugin($uri);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        if ($this->addPathPlugin) {
            $handler->insertAfter($this->addHostPlugin);

            return $this->addPathPlugin->process($request, $handler);
        }

        return $this->addHostPlugin->process($request, $handler);
    }
}
