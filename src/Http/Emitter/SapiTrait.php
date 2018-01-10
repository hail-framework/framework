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

trait SapiTrait
{
    /**
     * Checks to see if content has previously been sent.
     *
     * If either headers have been sent or the output buffer contains content,
     * raises an exception.
     *
     * @throws \RuntimeException if headers have already been sent.
     * @throws \RuntimeException if output is present in the output buffer.
     */
    private static function assertNoPreviousOutput()
    {
        if (\headers_sent()) {
            throw new \RuntimeException('Unable to emit response; headers already sent');
        }

        if (\ob_get_level() > 0 && \ob_get_length() > 0) {
            throw new \RuntimeException('Output has been emitted previously; cannot emit response');
        }
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is available, it, too, is emitted.
     *
     * It's important to mention that, in order to prevent PHP from changing
     * the status code of the emitted response, this method should be called
     * after `emitHeaders()`
     *
     * @param ResponseInterface $response
     *
     * @see SapiTrait::emitHeaders()
     */
    protected static function emitStatusLine(ResponseInterface $response)
    {
        $reasonPhrase = $response->getReasonPhrase();
        $statusCode = $response->getStatusCode();

        \header('HTTP/' . $response->getProtocolVersion() . ' ' . $statusCode .
            ($reasonPhrase ? ' ' . $reasonPhrase : ''),
            true, $statusCode
        );
    }

    /**
     * Emit response headers.
     *
     * Loops through each header, emitting each; if the header value
     * is an array with multiple values, ensures that each is sent
     * in such a way as to create aggregate headers (instead of replace
     * the previous).
     *
     * @param ResponseInterface $response
     */
    protected static function emitHeaders(ResponseInterface $response)
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $header => $values) {
            $first = true;
            foreach ($values as $value) {
                \header($header . ': ' . $value, $first, $statusCode);
                $first = false;
            }
        }
    }
}
