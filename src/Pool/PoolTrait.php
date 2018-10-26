<?php

namespace Hail\Pool;

use Psr\Container\ContainerInterface;

trait PoolTrait
{
    protected $pool;
    protected $worker;
    protected $autoRelease = true;

    public function __construct(array $config, ContainerInterface $container = null)
    {
        $this->pool = new Pool($config, $container);
    }

    public function __call($name, $arguments)
    {
        $worker = $this->worker ?? $this->pool->get();

        $return = $worker->$name(...$arguments);

        if ($this->autoRelease) {
            $this->pool->release($worker);

            if ($this->worker !== null) {
                $this->worker = null;
            }
        } else {
            $this->worker = $worker;
        }

        return $return;
    }

    public function setAutoRelease($auto = false): void
    {
        $this->autoRelease = $auto;
    }

    public function release(): bool
    {
        if ($this->worker) {
            $this->pool->release($this->worker);
            $this->worker = null;

            return true;
        }

        return false;
    }

    public function destroy()
    {
        if ($this->worker) {
            $this->pool->destroy();
            $this->worker = null;

            return true;
        }

        return false;
    }
}