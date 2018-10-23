<?php

namespace Hail\Swoole\Pool;

use Hail\Facade\Container;
use Hail\Container\Reflection;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use SplDoublyLinkedList;

class Pool
{
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
    protected $waiting;

    /**
     * @var bool
     */
    protected $channel = false;

    public function __construct($config)
    {
        if (!isset($config['worker'])) {
            throw new \RuntimeException('Worker config not defined');
        }

        $config['worker'] = $this->initWorker($config['worker']);

        $this->config = $config + [
                'min' => 2,
                'max' => 10,
            ];


        if (Coroutine::getuid() > 0) {
            $this->waiting = new Channel($this->config['max']);
            $this->channel = true;
        } else {
            $this->waiting = new SplDoublyLinkedList();
        }
    }

    protected function initWorker($config): array
    {
        if (\is_string($config)) {
            $config = [$config, []];
        } elseif (!\is_array($config) || !isset($config[0])) {
            throw new \InvalidArgumentException('Worker config must be string/array');
        }

        $worker = $config[0];
        $args = $config[1] ?? [];

        if (\is_callable($worker)) {
            $reflection = Reflection::createFromCallable($worker);

            $returnType = (string) $reflection->getReturnType();
            if ($returnType !== WorkerInterface::class) {
                throw new \InvalidArgumentException('Generated worker must be instance of WorkerInterface');
            }

            $params = $reflection->getParameters();

            return ['call', $worker, $args, $params];
        }

        if (\is_string($worker) && \class_exists($worker)) {
            if (!\is_a($worker, WorkerInterface::class, true)) {
                throw new \InvalidArgumentException('Generated worker must be instance of WorkerInterface');
            }

            $reflection = new \ReflectionClass($worker);
            $params = $reflection->getConstructor()->getParameters();

            return ['create', $worker, (array) $args, $params];
        }

        throw new \InvalidArgumentException('Worker must be class/callable');
    }

    protected function createWorker(): WorkerInterface
    {
        [$method, $worker, $args, $params] = $this->config['worker'];

        $instance = Container::$method($worker, $args, $params);
        if (!$instance instanceof WorkerInterface) {
            throw new \RuntimeException('Worker is not instance of WorkerInstance');
        }

        $instance
            ->setPool($this)
            ->setPoolIndex(++$this->index)
            ->setLastActive(time());

        return $instance;
    }


    public function getStats()
    {
        return [
            'count' => $this->count(),
            'waiting' => $this->waiting(),
            'active' => $this->active,
        ];
    }

    public function waiting()
    {
        if ($this->channel) {
            $count = $this->waiting->stats()['queue_num'];
        } else {
            $count = $this->waiting->count();
        }

        return $count < 0 ? 0 : $count;
    }

    public function active()
    {
        return $this->active;
    }

    // 获取当前总连接数
    public function count()
    {
        return $this->waiting() + $this->active;
    }

    // 放入连接
    protected function push(WorkerInterface $worker)
    {
        --$this->active;
        if ($this->waiting() < $this->config['max']) {
            $this->waiting->push($worker);

            return true;
        }

        return false;
    }

    protected function pop(): WorkerInterface
    {
        if ($this->channel) {
            $worker = $this->waiting->pop();
        } elseif ($this->waiting->valid()) {
            $worker = $this->waiting->pop();
        } else {
            throw new \OutOfRangeException('No worker can use');
        }

        ++$this->active;

        /** @var WorkerInterface $worker */
        $worker->setLastActive(time());

        return $worker;
    }

    public function get()
    {
        if ($this->waiting() > 0) {
            return $this->pop();
        }

        if ($this->count() >= $this->config['max']) {
            return $this->pop();
        }

        ++$this->active;

        return $this->createWorker();
    }

    public function release($connection)
    {
        $this->push($connection);
    }

    public function destroy()
    {
        --$this->active;
    }
}