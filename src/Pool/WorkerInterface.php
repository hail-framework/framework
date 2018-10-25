<?php

namespace Hail\Pool;


interface WorkerInterface
{
    public function setPool(Pool $pool): WorkerInterface;

    public function setLastActive(int $time): WorkerInterface;

    public function getLastActive(): int;

    public function release(): void;

    public function destroy(): void;
}