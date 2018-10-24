<?php

namespace Hail\Pool;


trait WorkerTrait
{
    /**
     * @var Pool
     */
    protected $__pool;

    /**
     * @var int
     */
    protected $__index;

    /**
     * @var int
     */
    protected $__time;

    public function setPool(Pool $pool): self
    {
        $this->__pool = $pool;

        return $this;
    }

    public function setPoolIndex(int $index): self
    {
        $this->__index = $index;

        return $this;
    }

    public function getPoolIndex(): int
    {
        return $this->__index;
    }

    public function setLastActive(int $time): self
    {
        $this->__time = $time;

        return $this;
    }

    public function getLastActive(): int
    {
        return $this->__time;
    }

    public function release(): void
    {
        $this->__pool->release($this);
    }
}