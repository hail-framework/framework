<?php

namespace Hail\Pool;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Hail\Container\Builder;
use Psr\Container\ContainerInterface;
use Hail\Pool\Exception\{
    OverflowException, TimeoutException
};

class Pool
{
    public const OVERFLOW_CREATE_NEW = 1;
    public const OVERFLOW_THROW_EXCEPTION = 2;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $config;


    /**
     * @var \SplQueue|Channel
     */
    protected $channel;

    /**
     * @var int
     */
    protected $active = 0;

    /**
     * @var int
     */
    protected $waiting = 0;

    /**
     * @var array
     */
    protected $workers;

    /**
     * @var bool
     */
    protected $isChannel = false;

    public function __construct(array $config, ContainerInterface $container = null)
    {
        if (!isset($config['worker'])) {
            throw new \InvalidArgumentException('Worker config not defined');
        }

        if (isset($config['min']) && $config['min'] < 0) {
            $config['min'] = 0;
        }

        if (isset($config['max']) && $config['max'] < $config['min']) {
            throw new \InvalidArgumentException('The "max" config must be higher than "min" config');
        }

        $this->container = $container;
        $config['worker'] = $this->builder($config['worker']);

        $this->config = $config + [
                'min' => 2,
                'max' => 10,
                'queue_wait' => 0, // only for \Swoole\Coroutine\Channel
                'max_wait' => 0,
                'overflow' => static::OVERFLOW_THROW_EXCEPTION,
            ];


        if (Coroutine::getuid() > 0) {
            $this->channel = new Channel($this->config['max']);
            $this->isChannel = true;
        } else {
            $this->channel = new \SplQueue();
        }
    }

    protected function builder($config): array
    {
        if (\is_string($config)) {
            $config = [$config, []];
        } elseif (!\is_array($config) || !isset($config[0])) {
            throw new \InvalidArgumentException('Worker config must be string/array');
        }

        $worker = $config[0];
        $args = $config[1] ?? [];
        $params = [];

        if (\is_callable($worker)) {
            if ($this->container) {
                $params = Builder::getCallableParameters($worker);
            }

            return ['call', $worker, (array) $args, $params];
        }

        if (\is_string($worker) && \class_exists($worker)) {
            if ($this->container) {
                $params = Builder::getClassParameters($worker);
            }

            return ['create', $worker, (array) $args, $params];
        }

        throw new \InvalidArgumentException('Worker must be class/callable');
    }

    protected function create()
    {
        [$method, $builder, $args, $params] = $this->config['worker'];

        if ($this->container) {
            return Builder::$method($this->container, $builder, $args, $params);
        }

        if ($method === 'create') {
            return new $builder(...$args);
        }

        return $builder(...$args);
    }

    public function waiting(): int
    {
        return $this->waiting;
    }

    public function active(): int
    {
        return $this->active;
    }

    public function count(): int
    {
        return $this->waiting + $this->active;
    }

    protected function push($worker): bool
    {
        --$this->active;
        if ($this->waiting() < $this->config['max']) {
            $this->channel->push([$worker, time()]);
            ++$this->waiting;

            return true;
        }

        return false;
    }

    protected function worker()
    {
        if ($this->waiting > $this->config['min']) {
            $now = time();
            $count = $this->waiting - $this->config['min'];
            for ($i = 0; $i < $count; ++$i) {
                [$worker, $lastActive] = $this->pop();
                if (($now - $lastActive) < $this->config['max_wait']) {
                    return $worker;
                }

                $worker = null;
                --$this->waiting;
            }
        }

        [$worker] = $this->pop();

        return $worker;
    }

    protected function pop(): ?array
    {
        if ($this->isChannel) {
            $wait = $this->config['queue_wait'];
            if ($wait === 0) {
                return $this->channel->pop();
            }

            if (false === ($worker = $this->channel->pop($wait))) {
                throw new TimeoutException('Pool queue waiting timeout: ' . $wait . 's');
            }

            return $worker;
        }

        if ($this->waiting > 0) {
            return $this->channel->shift();
        }

        return null;
    }

    public function get()
    {
        $worker = $this->worker();

        if ($worker === null) {
            if (
                $this->config['overflow'] === static::OVERFLOW_CREATE_NEW ||
                $this->count() < $this->config['max']
            ) {
                $worker = $this->create();
            } else {
                throw new OverflowException('Workers in the pool are all used');
            }
        } else {
            --$this->waiting;
        }

        ++$this->active;

        return $worker;
    }

    public function release($worker): void
    {
        $this->push($worker);
    }

    public function destroy(): void
    {
        --$this->active;
    }
}