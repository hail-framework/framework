<?php

namespace Hail\Http\Client;

use Hail\Http\Client\Exception\LoopException;
use Hail\Http\Matcher;
use Hail\Http\Client\Plugin\PluginInterface;
use Hail\Promise\PromiseInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;

class RequestHandler implements RequestHandlerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $plugins;

    /**
     * @var int
     */
    private $index = 0;

    /**
     * @var int
     */
    private $restartMax;

    /**
     * @var int
     */
    private $restartNum = 0;

    /**
     * @param PluginInterface[]|mixed[] $plugins    plugin stack (with at least one plugin component)
     * @param int                       $restartMax max restart
     * @param ContainerInterface|null   $container  optional plugin resolver:
     *                                              $container->get(string $name): MiddlewareInterface
     *
     * @throws \InvalidArgumentException if an empty middleware stack was given
     */
    public function __construct(array $plugins, int $restartMax, ContainerInterface $container = null)
    {
        if (empty($plugins)) {
            throw new \InvalidArgumentException('Empty middleware queue');
        }

        $this->plugins = $plugins;
        $this->container = $container;
        $this->restartMax = $restartMax;
    }

    /**
     * Dispatch the request, return a response.
     *
     * @param RequestInterface $request
     *
     * @return PromiseInterface
     */
    public function dispatch(RequestInterface $request): PromiseInterface
    {
        return $this->get($request)->process($request, $this);
    }


    public function handle(RequestInterface $request): PromiseInterface
    {
        $plugin = $this->get($request, true);
        if ($plugin === null) {
            throw new \RuntimeException('Middleware queue exhausted, with no response returned.');
        }

        return $plugin->process($request, $this);
    }

    public function restart(RequestInterface $request): PromiseInterface
    {
        if ($this->restartNum > $this->restartMax) {
            throw new LoopException('Too many restarts in plugin client', $request);
        }
        ++$this->restartNum;

        $this->index = 0;

        return $this->dispatch($request);
    }

    public function insertAfter(PluginInterface $plugin): void
    {
        \array_splice($this->plugins, $this->index, 0, $plugin);
    }

    /**
     * Return the current or next available middleware frame in the middleware.
     *
     * @param RequestInterface $request
     * @param bool             $next
     *
     * @return null|PluginInterface
     * @throws
     */
    protected function get(RequestInterface $request, bool $next = false): ?PluginInterface
    {
        $index = $next ? ++$this->index : $this->index;

        if (!isset($this->plugins[$index])) {
            return null;
        }

        $plugin = $this->plugins[$index];

        if (\is_array($plugin)) {
            $conditions = $plugin;
            $plugin = \array_pop($conditions);

            if (!Matcher\Factory::matches($conditions, $request)) {
                return $this->get($request, true);
            }
        }

        if (\is_string($plugin)) {
            if ($this->container === null) {
                throw new \RuntimeException("No valid middleware provided: $plugin");
            }

            $plugin = $this->container->get($plugin);
        }

        if (!$plugin instanceof PluginInterface) {
            throw new \RuntimeException('The plugin must be an instance of PluginInterface');
        }

        return $plugin;
    }
}
