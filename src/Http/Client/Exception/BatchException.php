<?php

namespace Hail\Http\Client\Exception;

use Hail\Http\Client\Polyfill\BatchResult;

/**
 * This exception is thrown when HttpClient::sendRequests led to at least one failure.
 *
 * It gives access to a BatchResult with the request-exception and request-response pairs.
 *
 * @author Márk Sági-Kazár <mark.sagikazar@gmail.com>
 */
final class BatchException extends TransferException
{
    /**
     * @var BatchResult
     */
    private $result;

    /**
     * @param BatchResult $result
     */
    public function __construct(BatchResult $result)
    {
        $this->result = $result;
    }

    /**
     * Returns the BatchResult that contains all responses and exceptions.
     *
     * @return BatchResult
     */
    public function getResult()
    {
        return $this->result;
    }
}
