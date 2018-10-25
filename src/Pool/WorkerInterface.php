<?php

namespace Hail\Pool;


interface WorkerInterface
{
    public function setPool(Pool $pool): self;

    public function setLastActive(int $time): self;

    public function getLastActive(): int;

    public function release(): void;

    public function destroy(): void;
}