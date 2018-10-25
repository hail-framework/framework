<?php

namespace Hail\Pool;


trait WorkerTrait
{
    /**
     * @var Pool
     */
    protected $pool;

    /**
     * @var int
     */
    protected $lastActiveTime;

    public function setPool(Pool $pool): WorkerInterface
    {
        $this->pool = $pool;

        return $this;
    }

    public function setLastActive(int $time): WorkerInterface
    {
        $this->lastActiveTime = $time;

        return $this;
    }

    public function getLastActive(): int
    {
        return $this->lastActiveTime;
    }

    public function release(): void
    {
        $this->pool->release($this);
    }

    public function destroy(): void
    {
        $this->pool = null;
    }
}