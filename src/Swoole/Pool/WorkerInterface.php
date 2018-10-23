<?php

namespace Hail\Swoole\Pool;


interface WorkerInterface
{
    public function setPool(Pool $pool): self;

    public function setPoolIndex(int $index): self;

    public function getPoolIndex(): int;

    public function setLastActive(int $time): self;

    public function getLastActive(): int;

    public function release(): void;
}