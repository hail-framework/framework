<?php

namespace Hail\Swoole\Pool;

use Hail\Facade\Container;
use Hail\Container\Reflection;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use SplDoublyLinkedList;

class Pool
{
    public const STRATEGY_LIFO = SplDoublyLinkedList::IT_MODE_LIFO;
    public const STRATEGY_FIFO = SplDoublyLinkedList::IT_MODE_FIFO;

    protected $config;
    protected $active;

    /**
     * @var \SplDoublyLinkedList|Channel
     */
    protected $waiting;
    protected $channel = false;

    public function __construct($config)
    {
        if (!isset($config['worker'])) {
            throw new \RuntimeException('Worker config not defined');
        }

        $config['worker'] = $this->initWorker($config['worker']);

        $this->config = $config + [
                'size' => 2,
                'max' => 10,
                'strategy' => static::STRATEGY_LIFO,
            ];


        if (
            $this->config['strategy'] === static::STRATEGY_FIFO &&
            Coroutine::getuid() > 0
        ) {
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
            $params = Reflection::createFromCallable($worker)->getParameters();

            return ['call', $worker, $args, $params];
        }

        if (\is_string($worker) && \class_exists($worker)) {
            if (\method_exists($worker, 'getInstance')) {
                return ['call', [$worker, 'getInstance'], [], []];
            }

            if (!\is_array($args)) {
                throw new \InvalidArgumentException('Worker config arguments must be array');
            }

            $reflection = new \ReflectionClass($worker);
            $params = $reflection->getConstructor()->getParameters();

            return ['create', $worker, $args, $params];
        }

        throw new \InvalidArgumentException('Worker must be class/callable');
    }

    protected function createWorker()
    {
        [$method, $worker, $args, $params] = $this->config['worker'];

        return Container::$method($worker, $args, $params);
    }


    // 获取连接池的统计信息
    public function getStats()
    {
        return [
            'count' => $this->count(),
            'waiting' => $this->waiting(),
            'active' => $this->active(),
        ];
    }

    // 获取队列中的连接数
    public function waiting()
    {
        if ($this->channel) {
            $count = $this->waiting->stats()['queue_num'];
        } else {
            $count = $this->waiting->count();
        }

        return $count < 0 ? 0 : $count;
    }

    // 获取活跃的连接数
    public function active()
    {
        return $this->active;
    }

    // 获取当前总连接数
    public function count()
    {
        return $this->waiting() + $this->active();
    }

    // 放入连接
    protected function push($connection)
    {
        --$this->active;
        if ($this->waiting() < $this->config['size']) {
            if ($this->channel) {
                return $this->waiting->push($connection);
            }

            $this->waiting->push($connection);

            return true;
        }

        return false;
    }

    // 弹出连接
    protected function pop()
    {
        if ($this->channel) {
            $connection = $this->waiting->pop();
        } elseif ($this->waiting->valid()) {
            if ($this->config['strategy'] === static::STRATEGY_LIFO) {
                $connection = $this->waiting->pop();
            } else {
                $connection = $this->waiting->shift();
            }
        } else {
            throw new \OutOfRangeException('No worker can use');
        }

        ++$this->active;

        return $connection;
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