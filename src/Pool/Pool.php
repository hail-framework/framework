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
            throw new \RuntimeException('Worker config not defined');
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
            $reflection = Builder::createFromCallable($worker);

            $returnType = (string) $reflection->getReturnType();
            if ($returnType !== WorkerInterface::class) {
                throw new \InvalidArgumentException('Generated worker must be instance of WorkerInterface');
            }

            if ($this->container) {
                $params = $reflection->getParameters();
            }

            return ['call', $worker, (array) $args, $params];
        }

        if (\is_string($worker) && \class_exists($worker)) {
            if (!\is_a($worker, WorkerInterface::class, true)) {
                throw new \InvalidArgumentException('Generated worker must be instance of WorkerInterface');
            }

            if ($this->container) {
                $params = Builder::getClassParameters($worker);
            }

            return ['create', $worker, (array) $args, $params];
        }

        throw new \InvalidArgumentException('Worker must be class/callable');
    }

    protected function create(): WorkerInterface
    {
        [$method, $builder, $args, $params] = $this->config['worker'];

        if ($this->container) {
            $worker = Builder::$method($this->container, $builder, $args, $params);
        } elseif ($method === 'create') {
            $worker = new $builder(...$args);
        } else {
            $worker = $builder(...$args);
        }

        /** @var WorkerInterface $worker */
        $worker->setPool($this);

        return $worker;
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

    protected function push(WorkerInterface $worker): bool
    {
        --$this->active;
        if ($this->waiting() < $this->config['max']) {
            $this->channel->push(
                $worker->setLastActive(time())
            );
            ++$this->waiting;

            return true;
        }

        return false;
    }

    protected function worker(): ?WorkerInterface
    {
        if ($this->waiting > $this->config['min']) {
            $now = time();
            $count = $this->waiting - $this->config['min'];
            for ($i = 0; $i < $count; ++$i) {
                /** @var WorkerInterface $worker */
                $worker = $this->pop();
                if (($now - $worker->getLastActive()) < $this->config['max_wait']) {
                    return $worker;
                }

                --$this->waiting;
                $worker->destroy();
            }
        }

        return $this->pop();
    }

    protected function pop(): ?WorkerInterface
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

    public function get(): WorkerInterface
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

    public function release(WorkerInterface $worker): void
    {
        $this->push($worker);
    }

    public function destroy(): void
    {
        --$this->active;
    }
}