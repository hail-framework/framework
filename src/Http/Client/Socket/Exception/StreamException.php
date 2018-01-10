<?php

namespace Hail\Http\Client\Socket\Exception;

use Psr\Http\Client\ClientException;
use Psr\Http\Message\RequestInterface;

class StreamException extends \RuntimeException implements ClientException
{
    /**
     * The request object.
     *
     * @var RequestInterface
     */
    private $request;

    /**
     * Accepts an optional request object as 4th param.
     *
     * @param string           $message
     * @param int              $code
     * @param ClientException  $previous
     * @param RequestInterface $request
     */
    public function __construct($message = null, $code = null, $previous = null, RequestInterface $request = null)
    {
        $this->request = $request;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return \Psr\Http\Message\RequestInterface|null
     */
    final public function getRequest()
    {
        return $this->request;
    }
}
