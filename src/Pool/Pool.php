<?php

namespace Hail\Pool;

use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Hail\Util\Builder;
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
     * @var int
     */
    protected $active;


    /**
     * @var int
     */
    protected $index = 0;

    /**
     * @var \SplDoublyLinkedList|Channel
     */
    protected $channel;

    /**
     * @var int
     */
    protected $waiting;

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
        $config['worker'] = $this->workerBuilder($config['worker']);

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
            $this->channel = new \SplDoublyLinkedList();
        }
    }

    protected function workerBuilder($config): array
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

    protected function createWorker(): WorkerInterface
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
        $worker
            ->setPool($this)
            ->setPoolIndex(++$this->index);

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

    protected function pop(): ?WorkerInterface
    {
        $worker = null;
        if ($this->isChannel) {
            $wait = $this->config['queue_wait'];
            if ($wait === 0) {
                $worker = $this->channel->pop();
            } elseif (false === ($worker = $this->channel->pop($wait))) {
                throw new TimeoutException('Pool queue waiting timeout: ' . $wait . 's');
            }
        } elseif (!$this->channel->isEmpty()) {
            $worker = $this->channel->pop();
        }

        return $worker;
    }

    public function get(): WorkerInterface
    {
        $worker = $this->pop();

        if ($worker === null) {
            if (
                $this->config['overflow'] === static::OVERFLOW_CREATE_NEW ||
                $this->count() < $this->config['max']
            ) {
                $worker = $this->createWorker();
            } else {
                throw new OverflowException('Workers in the pool are all used');
            }
        }

        --$this->waiting;
        ++$this->active;

        if ($this->waiting > $this->config['min']) {

        }

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