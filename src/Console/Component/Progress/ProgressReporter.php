<?php
namespace Hail\Console\Component\Progress;

interface ProgressReporter
{
    public function update($finishedValue, $totalValue);
}
