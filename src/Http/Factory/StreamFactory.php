<?php

declare(strict_types=1);

namespace Hail\Http\Factory;

use Hail\Http\Factory;
use Interop\Http\Factory\StreamFactoryInterface;

/**
 * @author Feng Hao <flyinghail@msn.com>
 */
class StreamFactory implements StreamFactoryInterface
{
    public function createStream($body = null)
    {
        return Factory::stream($body ?? '');
    }

    public function createStreamFromFile($file, $mode = 'r')
    {
        return Factory::streamFromFile($file, $mode);
    }

    public function createStreamFromResource($resource)
    {
        return Factory::stream($resource);
    }
}
