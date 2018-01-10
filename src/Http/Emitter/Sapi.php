<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015-2016 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace Hail\Http\Emitter;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class Sapi implements EmitterInterface
{
    use SapiTrait;

    /**
     * Emits a response for a PHP SAPI environment.
     *
     * Emits the status line and headers via the header() function, and the
     * body content via the output buffer.
     *
     * @param ResponseInterface $response
     */
    public static function emit(ResponseInterface $response)
    {
        static::assertNoPreviousOutput();

        static::emitHeaders($response);
        static::emitStatusLine($response);
        static::emitBody($response);
    }

    /**
     * Emit the message body.
     *
     * @param ResponseInterface $response
     */
    protected static function emitBody(ResponseInterface $response)
    {
        echo $response->getBody();
    }
}
